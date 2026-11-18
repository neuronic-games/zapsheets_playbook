// CardBoard — card designer. Sizes and positions are in points (1/72 in) so cards print at true size.
//
// Element types (left panel):
//   TEXT       kind 'text'   — fixed wording; may include {Column} placeholders
//   DATA       kind 'data'   — one column of the sheet; shown as text (default) or as an image
//   IMAGE      kind 'image'  — a fixed picture from a web address
//   SHAPE      kind 'shape'  — rect, round, ellipse, line, triangle, diamond, hexagon, star
//   GENERATED  kind 'gen'    — number sequence, random number, or random pick from a list
'use strict';

// ── State ─────────────────────────────────────────────────────────────────────
var TABS = [], LAYOUTS = {}, ROWS = [], FIELDS = [];
var curTab = '', layout = null, rowIdx = 0, selId = null, zoom = 1, dirty = false;
var undoStack = [], redoStack = [];
var side = 'front';   // which face of the card is being designed
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
var SHAPES = [
  ['rect', 'Rectangle'], ['round', 'Rounded'], ['ellipse', 'Circle'], ['line', 'Line'],
  ['triangle', 'Triangle'], ['diamond', 'Diamond'], ['hexagon', 'Hexagon'], ['star', 'Star']
];
var GENS = [['seq', '123', 'Number sequence'], ['rand', '🎲', 'Random number'], ['list', '☰', 'Random from list']];
var IMG_ICON = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
var FLIP_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/></svg>';
var UPLOADING = {};   // element id → true while its picture uploads
var KIND_NAME = { text: 'Text', data: 'Data', image: 'Image', shape: 'Shape', gen: 'Generated' };
var PX = 96 / 72;   // CSS px per pt
var _activeTab = 'design';

function newLayout() {
  return { v: 3, card: { size: 'poker', w: 180, h: 252, bg: '#ffffff', radius: 9, borderColor: '#222222', borderW: 0 }, els: [], back: { bg: '#2e2763', els: [] } };
}
function uid() { return 'e' + Math.random().toString(36).slice(2, 9); }
function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function $(id) { return document.getElementById(id); }
function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
function r1(v) { return Math.round(v * 10) / 10; }
function isTextLike(e) { return e.kind === 'text' || e.kind === 'gen' || (e.kind === 'data' && e.as !== 'image'); }
// The front is the layout itself; the back is layout.back ({bg, els})
function faceOf(lay, s) { return s === 'back' ? lay.back : lay; }
function curEls() { return faceOf(layout, side).els; }
// Does the back change from card to card (column values, generated values)?
function backVaries(lay) {
  return (lay || layout).back.els.some(function(e) { return e.kind === 'data' || e.kind === 'gen' || (e.kind === 'text' && /\{[^{}]+\}/.test(e.text || '')); });
}
function isImageLike(e) { return e.kind === 'image' || (e.kind === 'data' && e.as === 'image'); }

// Layouts saved by the first version: box → shape, "{Column}" text/image → data
function normalize(lay) {
  if (!lay.card) lay.card = newLayout().card;
  function fix(e) {
    var one = /^\{([^{}]+)\}$/;
    if (e.kind === 'box') {
      e = Object.assign(e, { kind: 'shape', shape: e.radius ? 'round' : 'rect', fill: e.bg || '', stroke: e.borderColor || '#000000', strokeW: e.borderW || 0 });
      delete e.bg; delete e.borderW; delete e.borderColor;
    } else if (e.kind === 'text' && one.test(e.text || '')) {
      e.kind = 'data'; e.field = e.text.match(one)[1]; e.as = 'text'; delete e.text;
    } else if (e.kind === 'image' && one.test(e.src || '')) {
      e.kind = 'data'; e.field = e.src.match(one)[1]; e.as = 'image'; delete e.src;
    }
    return e;
  }
  lay.els = (lay.els || []).map(fix);
  if (!lay.back) lay.back = { bg: '#2e2763', els: [] };
  var ob = lay.card.back;   // briefly kept on the card by the first Export version
  if (ob) {
    lay.back.bg = ob.bg || lay.back.bg;
    if (ob.src && !lay.back.els.length) lay.back.els.push({ id: uid(), kind: 'image', src: ob.src, fit: ob.fit || 'cover', x: 0, y: 0, w: lay.card.w, h: lay.card.h, z: 1 });
    delete lay.card.back;
  }
  lay.back.els = (lay.back.els || []).map(fix);
  lay.v = 3;
  return lay;
}

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
// Links are shown straight from their source (never copied to our server), so
// changing the picture in Dropbox or Drive changes it on the cards too.
function imageUrl(v) {
  v = cleanVal(v).trim();
  var m = v.match(/^=IMAGE\(\s*"([^"]+)"/i); if (m) v = m[1];
  m = v.match(/^\[[^\]]*\]\(([^)]+)\)$/); if (m) v = m[1];
  // Google Drive: /file/d/ID/view, open?id=ID, uc?id=ID, thumbnail?id=ID → an embeddable image
  m = v.match(/(?:drive|docs)\.google\.com\/(?:.*\/)?(?:file\/d\/|open\?id=|uc\?(?:export=\w+&)?id=|thumbnail\?id=)([A-Za-z0-9_-]{10,})/);
  if (m) v = 'https://drive.google.com/thumbnail?id=' + m[1] + '&sz=w2000';
  // Dropbox: share links (dl=0) show a page; raw=1 serves the image itself
  if (/^https?:\/\/(www\.)?dropbox\.com\//i.test(v)) {
    v = /[?&](dl|raw)=/.test(v) ? v.replace(/([?&])(dl|raw)=[^&#]*/, '$1raw=1') : v + (v.indexOf('?') < 0 ? '?' : '&') + 'raw=1';
  }
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
    if (v) { n++; if (imageUrl(v) && /\.(png|jpe?g|gif|webp|svg)(\?|$|&)|=IMAGE|drive\.google|googleusercontent|dropbox(usercontent)?\.com/i.test(v)) img++; }
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

// ── Generated values (seeded, so the preview and the printout match) ──────────
function seeded(str) {
  var h = 1779033703 ^ str.length;
  for (var i = 0; i < str.length; i++) { h = Math.imul(h ^ str.charCodeAt(i), 3432918353); h = (h << 13) | (h >>> 19); }
  h = Math.imul(h ^ (h >>> 16), 2246822507); h = Math.imul(h ^ (h >>> 13), 3266489909);
  return ((h ^= h >>> 16) >>> 0) / 4294967296;
}
function genItems(e) { return String(e.items || '').split(/\n|,/).map(function(s) { return s.trim(); }).filter(Boolean); }
function genValue(e, ctx) {
  var i = (e.per === 'row' ? ctx.row : ctx.card) || 0, seed = (e.seed || e.id) + ':' + i;
  if (e.mode === 'rand') {
    var lo = Math.round(+e.min || 0), hi = Math.round(+e.max || 0); if (hi < lo) { var t = lo; lo = hi; hi = t; }
    return String(lo + Math.floor(seeded(seed) * (hi - lo + 1)));
  }
  if (e.mode === 'list') {
    var it = genItems(e); if (!it.length) return '';
    return e.pick === 'cycle' ? it[i % it.length] : it[Math.floor(seeded(seed) * it.length)];
  }
  var n = (+e.start || 0) + (+e.step || 0) * i, s = String(Math.abs(n));
  while (s.length < (+e.pad || 0)) s = '0' + s;
  return (e.prefix || '') + (n < 0 ? '-' : '') + s + (e.suffix || '');
}

// ── Rendering one card (designer and print share this) ───────────────────────
function cardStyle(c) {
  return 'width:' + c.w + 'pt;height:' + c.h + 'pt;background:' + (c.bg || '#fff') + ';border-radius:' + (c.radius || 0) + 'pt;' +
         (c.borderW ? 'box-shadow:inset 0 0 0 ' + c.borderW + 'pt ' + (c.borderColor || '#000') + ';' : '');
}
function elStyle(e) {
  var s = 'left:' + e.x + 'pt;top:' + e.y + 'pt;width:' + e.w + 'pt;height:' + e.h + 'pt;z-index:' + (e.z || 1) + ';';
  if (e.rot) s += 'transform:rotate(' + e.rot + 'deg);';
  if (e.opacity != null && e.opacity < 1) s += 'opacity:' + e.opacity + ';';
  if (e.kind === 'shape') return s;
  if (e.bg) s += 'background:' + e.bg + ';';
  if (e.borderW) s += 'border:' + e.borderW + 'pt solid ' + (e.borderColor || '#000') + ';';
  if (e.radius) s += 'border-radius:' + e.radius + 'pt;';
  if (isTextLike(e)) {
    s += 'font-family:\'' + (e.font || 'DINRegular') + '\',Arial,sans-serif;font-size:' + (e.size || 12) + 'pt;color:' + (e.color || '#111') + ';' +
         'text-align:' + (e.align || 'left') + ';padding:' + (e.pad == null ? 2 : e.pad) + 'pt;line-height:' + (e.lh || 1.15) + ';' +
         (e.bold ? 'font-weight:700;' : 'font-weight:400;') + (e.italic ? 'font-style:italic;' : '') + (e.upper ? 'text-transform:uppercase;' : '');
  }
  return s;
}
function shapeSVG(e) {
  var w = e.w, h = e.h, sw = +e.strokeW || 0, hs = sw / 2, f = e.fill || 'none', st = sw ? (e.stroke || '#000') : 'none';
  var a = ' fill="' + esc(f) + '" stroke="' + esc(st) + '" stroke-width="' + sw + '" stroke-linejoin="round"', body = '';
  function pts(p) { return '<polygon points="' + p.map(function(q) { return r1(q[0]) + ',' + r1(q[1]); }).join(' ') + '"' + a + '/>'; }
  switch (e.shape) {
    case 'ellipse':  body = '<ellipse cx="' + w / 2 + '" cy="' + h / 2 + '" rx="' + Math.max(0, w / 2 - hs) + '" ry="' + Math.max(0, h / 2 - hs) + '"' + a + '/>'; break;
    case 'line':     body = '<line x1="0" y1="' + h / 2 + '" x2="' + w + '" y2="' + h / 2 + '" stroke="' + esc(e.stroke || '#000') + '" stroke-width="' + (sw || 1) + '" stroke-linecap="round"/>'; break;
    case 'triangle': body = pts([[w / 2, hs], [w - hs, h - hs], [hs, h - hs]]); break;
    case 'diamond':  body = pts([[w / 2, hs], [w - hs, h / 2], [w / 2, h - hs], [hs, h / 2]]); break;
    case 'hexagon':  body = pts([[w * .25, hs], [w * .75, hs], [w - hs, h / 2], [w * .75, h - hs], [w * .25, h - hs], [hs, h / 2]]); break;
    case 'star': {
      var p = [], rx = w / 2 - hs, ry = h / 2 - hs;
      for (var i = 0; i < 10; i++) { var ang = -Math.PI / 2 + i * Math.PI / 5, k = i % 2 ? .45 : 1; p.push([w / 2 + Math.cos(ang) * rx * k, h / 2 + Math.sin(ang) * ry * k]); }
      body = pts(p); break;
    }
    default: {
      var r = e.shape === 'round' ? (e.radius == null ? 10 : +e.radius) : (+e.radius || 0);
      body = '<rect x="' + hs + '" y="' + hs + '" width="' + Math.max(0, w - sw) + '" height="' + Math.max(0, h - sw) + '" rx="' + r + '" ry="' + r + '"' + a + '/>';
    }
  }
  return '<svg width="100%" height="100%" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" style="display:block;overflow:visible">' + body + '</svg>';
}
function imgDiv(u, e, label, ctx) {
  if (u && ctx && ctx.proxy) u = proxied(u);
  if (!u && !label) return '';
  if (!u) return '<div class="el-img" style="background:#eef0f6;display:flex;align-items:center;justify-content:center;color:#a0a4bb;font-size:8pt;text-align:center">' + esc(label) + '</div>';
  // A real <img>, not a CSS background: browsers leave backgrounds out when printing
  var fit = e.fit === 'stretch' ? 'fill' : (e.fit || 'cover');
  return '<img class="el-img" src="' + esc(u) + '" alt="" draggable="false" referrerpolicy="no-referrer" style="object-fit:' + fit + '">';
}
function elInner(e, row, ctx) {
  ctx = ctx || { card: 0, row: 0 };
  switch (e.kind) {
    case 'text': {
      var t = fill(e.text, row);
      return t.trim() ? esc(t) : (row ? '' : '<span class="el-empty">' + esc(e.text || 'Text') + '</span>');
    }
    case 'data':
      if (e.as === 'image') return imgDiv(row ? imageUrl(row[e.field]) : '', e, ctx.designing ? e.field : '', ctx);
      return row ? esc(textVal(row[e.field])) : '<span class="el-empty">' + esc(e.field) + '</span>';
    case 'image': return imageUrl(e.src) || ctx && ctx.designing ? imgDiv(imageUrl(e.src), e, 'Drop an image here, or click the picture button', ctx) : '';
    case 'shape': return shapeSVG(e);
    case 'gen':   return esc(genValue(e, ctx));
  }
  return '';
}
function renderCardHTML(lay, row, designing, ctx) {
  ctx = Object.assign({ card: 0, row: 0 }, ctx || {}, { designing: !!designing });
  var face = faceOf(lay, ctx.side), cs = ctx.side === 'back' ? Object.assign({}, lay.card, { bg: lay.back.bg }) : lay.card;
  var h = '<div class="card' + (designing ? ' designing' : '') + (ctx.side === 'back' ? ' back' : '') + '" style="' + cardStyle(cs) + '">';
  face.els.slice().sort(function(a, b) { return (a.z || 1) - (b.z || 1); }).forEach(function(e) {
    var cls = 'el k-' + e.kind + (e.valign === 'middle' ? ' v-middle' : e.valign === 'bottom' ? ' v-bottom' : '') + (designing && e.id === selId ? ' picked' : '');
    if (designing && UPLOADING[e.id]) cls += ' img-loading';
    h += '<div class="' + cls + '" data-id="' + e.id + '" style="' + elStyle(e) + '">';
    h += designing
      ? '<div class="el-inner">' + elInner(e, row, ctx) + '</div>' +
        (e.kind === 'image' ? '<button type="button" class="img-btn" title="Change image" onclick="openImagePicker(event,\'' + e.id + '\')">' + IMG_ICON + '</button>' : '') +
        ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].map(function(d) { return '<span class="hdl ' + d + '" data-h="' + d + '"></span>'; }).join('')
      : elInner(e, row, ctx);
    h += '</div>';
  });
  if (designing && !face.els.length) h += '<div class="card-empty-hint">' + (ctx.side === 'back' ? 'The back of the card — drag elements here' : 'Drag elements here') + '</div>';
  return h + '</div>';
}

// ── Loading ───────────────────────────────────────────────────────────────────
function init() {
  renderPalette();
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
  layout = normalize(LAYOUTS[name] ? JSON.parse(JSON.stringify(LAYOUTS[name])) : newLayout());
  setDirty(false);
  // An unsaved draft from this browser wins (restored, still marked unsaved)
  try {
    var d = localStorage.getItem('cb_draft:' + SHEET_ID + ':' + name);
    if (d && d !== JSON.stringify(layout)) { layout = normalize(JSON.parse(d)); setDirty(true, true); toast('Restored your unsaved changes'); }
  } catch (e) {}
  $('dataList').innerHTML = '<div class="muted">Loading…</div>';
  $('cardHolder').innerHTML = '<div class="loading" style="width:240px;height:200px"><span class="spin"></span>Loading rows…</div>';
  api('rows', { tab: name, force: force ? '1' : '' })
    .then(function(res) {
      if (curTab !== name) return;
      ROWS = (res.rows || []).filter(function(r) { return Object.keys(r).some(function(k) { return k.charAt(0) !== '_' && String(r[k]).trim(); }); });
      FIELDS = [];
      ROWS.forEach(function(r) { Object.keys(r).forEach(function(k) { if (k.charAt(0) !== '_' && FIELDS.indexOf(k) < 0) FIELDS.push(k); }); });
      renderData(); fitZoom(); renderAttrs();
      if (_activeTab === 'print') renderPrint();
      if (_activeTab === 'export') renderExport();
      if (force) toast('✓ Reloaded from the sheet');
    })
    .catch(function(e) {
      ROWS = []; FIELDS = []; renderData(); render(); renderAttrs();
      toast('Couldn\'t read "' + name + '" — ' + e.message, true);
    });
}
function reloadSheet() { if (curTab) selectTab(curTab, true, true); }
function openInSheets() { window.open('https://docs.google.com/spreadsheets/d/' + SHEET_ID + '/edit', '_blank', 'noopener'); }

// ── Left panel: TEXT · DATA · IMAGE · SHAPE · GENERATED ──────────────────────
function chip(attrs, ico, name, sample, extraCls) {
  return '<div class="field-chip' + (extraCls ? ' ' + extraCls : '') + '" draggable="true" ' + attrs + ' ondragstart="chipDragStart(event)" onclick="chipClick(this)" title="Drag onto the card, or click to add">' +
         '<span class="ico">' + ico + '</span><span class="nm">' + esc(name) + '</span>' + (sample ? '<span class="sample">' + esc(sample) + '</span>' : '') + '</div>';
}
// Each section can be folded away; the choice is remembered in this browser
function paletteClosed() { try { return JSON.parse(localStorage.getItem('cb_closed') || '{}'); } catch (e) { return {}; } }
function section(key, title, body, count) {
  var closed = !!paletteClosed()[key];
  return '<div class="pal-sec' + (closed ? ' closed' : '') + '" data-sec="' + key + '">' +
         '<button type="button" class="pal-head" onclick="toggleSection(\'' + key + '\')"><span class="chev">▾</span>' + title +
         (count != null ? '<span class="pal-count" id="' + key + 'Count">' + count + '</span>' : '') + '</button>' +
         '<div class="pal-body">' + body + '</div></div>';
}
function toggleSection(key) {
  var c = paletteClosed(); c[key] = !c[key];
  try { localStorage.setItem('cb_closed', JSON.stringify(c)); } catch (e) {}
  var el = document.querySelector('.pal-sec[data-sec="' + key + '"]'); if (el) el.classList.toggle('closed', !!c[key]);
}
function renderPalette() {
  var h = '';
  h += section('text', 'Text', chip('data-add="text"', 'T', 'Text'));
  h += section('data', 'Data', '<div id="dataList"><div class="muted">Loading…</div></div>', '');
  h += section('image', 'Image', chip('data-add="image"', IMG_ICON, 'Image'));
  h += section('shape', 'Shape', '<div class="shape-grid">' + SHAPES.map(function(s) {
    var demo = { shape: s[0], w: 24, h: s[0] === 'line' ? 24 : 18, fill: s[0] === 'line' ? 'none' : '#ece9ff', stroke: '#4b3fa8', strokeW: 1.5, radius: s[0] === 'round' ? 5 : 0 };
    return '<div class="shape-chip" draggable="true" data-shape="' + s[0] + '" ondragstart="chipDragStart(event)" onclick="chipClick(this)" title="' + s[1] + '">' +
           '<span class="shape-ico">' + shapeSVG(demo) + '</span><span>' + s[1] + '</span></div>';
  }).join('') + '</div>');
  h += section('gen', 'Generated', GENS.map(function(g) { return chip('data-gen="' + g[0] + '"', g[1], g[2]); }).join(''));
  h += '<p class="muted" style="margin-top:.8rem">Drag onto the card, or click to add it in the middle. Drop a picture file anywhere on the card to add it as an image.</p>';
  $('palette').innerHTML = h;
}
function renderData() {
  var el = $('dataList'); if (!el) return;
  if (!FIELDS.length) { el.innerHTML = '<div class="muted">No columns found. The first row of the sheet should hold the column names, with one card per row below.</div>'; return; }
  var sample = ROWS[rowIdx] || ROWS[0] || {};
  if ($('dataCount')) $('dataCount').textContent = FIELDS.length;
  el.innerHTML = FIELDS.map(function(f) { return chip('data-field="' + esc(f) + '"', '≡', f, looksLikeImage(f) ? '' : textVal(sample[f])); }).join('');
}
function chipSpec(c) {
  if (c.dataset.field) return 'data:' + c.dataset.field;
  if (c.dataset.shape) return 'shape:' + c.dataset.shape;
  if (c.dataset.gen)   return 'gen:' + c.dataset.gen;
  return 'add:' + c.dataset.add;
}
function chipDragStart(ev) { ev.dataTransfer.setData('text/plain', chipSpec(ev.currentTarget)); ev.dataTransfer.effectAllowed = 'copy'; }
function chipClick(c) {
  if (!layout) return;
  var spec = chipSpec(c);
  addAt(spec, layout.card.w / 2, layout.card.h / 2);
  if (spec === 'add:image') setTimeout(function() { openImagePicker(null, selId); }, 0);   // straight to choosing the picture
}

// A new element for a palette item, centred on (x, y)
function makeEl(spec, x, y) {
  var c = layout.card, i = spec.indexOf(':'), type = spec.slice(0, i), arg = spec.slice(i + 1), e;
  var textStyle = { font: 'DINRegular', size: 11, color: '#1b1b2b', align: 'left' };
  if (type === 'data') {
    // Size the box from the longest value in this column (rough DIN Medium metrics: ~0.55 em per character)
    var size = 11, longest = 0, lines = 1, maxW = c.w - 24;
    ROWS.forEach(function(r) { var v = textVal(r[arg]); v.split('\n').forEach(function(l) { longest = Math.max(longest, l.length); }); lines = Math.max(lines, v.split('\n').length); });
    var w = Math.round(clamp(longest * size * .55 + 8, 28, maxW)), wrapped = 0;
    ROWS.forEach(function(r) { var n = 0; textVal(r[arg]).split('\n').forEach(function(l) { n += Math.max(1, Math.ceil((l.length * size * .55 + 4) / w)); }); wrapped = Math.max(wrapped, n); });
    e = Object.assign({ kind: 'data', field: arg, as: 'text', w: w, h: Math.round(clamp(Math.max(lines, wrapped) * size * 1.15 + 6, 18, c.h - 24)) }, textStyle);
  } else if (type === 'add' && arg === 'text') {
    e = Object.assign({ kind: 'text', text: 'Text', w: c.w - 24, h: 26 }, textStyle, { font: 'DINBlack', size: 14, align: 'center' });
  } else if (type === 'add' && arg === 'image') {
    e = { kind: 'image', src: '', fit: 'contain', w: Math.round(c.w * .5), h: Math.round(c.w * .5) };
  } else if (type === 'shape') {
    var sq = Math.round(c.w * .4);
    e = { kind: 'shape', shape: arg, fill: arg === 'line' ? '' : '#ece9ff', stroke: '#4b3fa8', strokeW: arg === 'line' ? 2 : 0,
          w: arg === 'line' ? c.w - 40 : (/^(rect|round)$/.test(arg) ? c.w - 40 : sq), h: arg === 'line' ? 8 : (/^(rect|round)$/.test(arg) ? 50 : sq) };
    if (arg === 'round') e.radius = 10;
  } else if (type === 'gen') {
    e = Object.assign({ kind: 'gen', mode: arg, per: 'card', w: 50, h: 22 }, textStyle, { font: 'DINBlack', size: 12, align: 'center', valign: 'middle' });
    if (arg === 'seq')  Object.assign(e, { start: 1, step: 1, pad: 0, prefix: '#', suffix: '' });
    if (arg === 'rand') Object.assign(e, { min: 1, max: 6 });
    if (arg === 'list') Object.assign(e, { items: 'Red\nGreen\nBlue', pick: 'random', w: 70 });
  }
  if (!e) return null;
  e.x = x - e.w / 2; e.y = y - e.h / 2;
  return e;
}
function addAt(spec, x, y) { var e = makeEl(spec, x, y); if (e) addElementAt(e); }
function addElementAt(e) {
  var c = layout.card;
  e.id = uid(); e.z = curEls().reduce(function(m, x) { return Math.max(m, x.z || 1); }, 0) + 1;
  e.w = clamp(e.w, 4, c.w); e.h = clamp(e.h, 4, c.h);
  e.x = r1(clamp(e.x, 0, c.w - e.w)); e.y = r1(clamp(e.y, 0, c.h - e.h));
  snapshot(); curEls().push(e); selId = e.id; changed();
}

// ── Pictures: upload a file, or link to Dropbox / Google Drive / the web ──────
// Uploaded files are stored with the app (push/uploadImage.php — the same as
// GameBoard). Links are kept as links and loaded from their source every time.
function isImageFile(f) { return f && /^image\/(png|jpe?g|gif|webp)$/i.test(f.type); }
function droppedFile(dt) { return dt && dt.files && Array.prototype.filter.call(dt.files, isImageFile)[0]; }
function droppedLink(dt) {
  if (!dt) return '';
  var u = (dt.getData('text/uri-list') || '').split('\n').filter(function(l) { return l && l.charAt(0) !== '#'; })[0] || '';
  if (!u) { var hm = (dt.getData('text/html') || '').match(/<img[^>]+src="([^"]+)"/i); if (hm) u = hm[1].replace(/&amp;/g, '&'); }
  if (!u) { var t = (dt.getData('text/plain') || '').trim(); if (/^https?:\/\/\S+$/i.test(t)) u = t; }
  return /^https?:\/\//i.test(u) ? u.trim() : '';
}
function setImageSrc(id, src) {
  var e = elById(id); if (!e) return;
  snapshot(); e.src = src; changed(selId !== id);
  if (selId === id) renderAttrs();
}
function uploadImage(id, file) {
  if (!isImageFile(file)) { toast('Use a PNG, JPG, GIF or WebP picture', true); return; }
  if (file.size > 10 * 1024 * 1024) { toast('That picture is over 10 MB', true); return; }
  UPLOADING[id] = true; render(); if (selId === id) renderAttrs();
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('file', file);
  fetch(APP_BASE + 'push/uploadImage.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(r) {
      if (!r || r.error || !r.url) throw new Error(r && r.error ? r.error : 'Upload failed');
      delete UPLOADING[id]; setImageSrc(id, r.url);
    })
    .catch(function(err) { delete UPLOADING[id]; render(); toast('Couldn\'t upload — ' + err.message, true); });
}
function closeImagePicker() { var p = $('imgPicker'); if (p) p.remove(); }
function openImagePicker(ev, id) {
  if (ev) { ev.preventDefault(); ev.stopPropagation(); }
  var e = elById(id); if (!e) return;
  if (selId !== id) selectEl(id);
  closeImagePicker();
  var p = document.createElement('div');
  p.id = 'imgPicker'; p.className = 'img-picker';
  p.innerHTML =
    '<div class="ip-drop" id="ipDrop">' + IMG_ICON + '<b>Drop a picture here</b><span>or</span>' +
      '<button type="button" class="ip-choose" onclick="$(\'ipFile\').click()">Choose a file…</button>' +
      '<input type="file" id="ipFile" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none"></div>' +
    '<span class="mini">Or a web address</span>' +
    '<div class="ip-url"><input class="inp" id="ipUrl" placeholder="Dropbox, Google Drive or web link" value="' + esc(/\/images\/[A-Za-z0-9_\-]+\/[0-9a-f]{20}\.\w+$/.test(e.src || '') ? '' : (e.src || '')) + '">' +
      '<button type="button" class="attr-btn" id="ipUse">Use</button></div>' +
    '<p class="muted">Links are shown straight from Dropbox, Drive or the website, so when you change the picture there it changes here too. In Drive, share it as “Anyone with the link”.</p>' +
    (e.src ? '<button type="button" class="attr-btn danger ip-remove" id="ipRemove">Remove picture</button>' : '');
  document.body.appendChild(p);
  // Place it beside the picture button (or the element), kept on screen
  var anchor = $('cardHolder').querySelector('.el[data-id="' + id + '"]'), r = anchor ? anchor.getBoundingClientRect() : { right: innerWidth / 2, top: 100 };
  var left = Math.min(r.right + 10, innerWidth - p.offsetWidth - 12), top = clamp(r.top, 70, innerHeight - p.offsetHeight - 12);
  if (left < r.right && anchor) left = Math.max(12, r.left - p.offsetWidth - 10);
  p.style.left = left + 'px'; p.style.top = top + 'px';
  var drop = $('ipDrop');
  drop.addEventListener('dragover', function(d) { d.preventDefault(); drop.classList.add('over'); });
  drop.addEventListener('dragleave', function() { drop.classList.remove('over'); });
  drop.addEventListener('drop', function(d) {
    d.preventDefault(); d.stopPropagation(); drop.classList.remove('over');
    var f = droppedFile(d.dataTransfer), l = droppedLink(d.dataTransfer);
    if (f) { closeImagePicker(); uploadImage(id, f); } else if (l) { closeImagePicker(); setImageSrc(id, l); }
  });
  $('ipFile').addEventListener('change', function() { var f = this.files[0]; if (f) { closeImagePicker(); uploadImage(id, f); } });
  function useUrl() {
    var u = $('ipUrl').value.trim();
    if (u && !imageUrl(u)) { toast('That doesn\'t look like a web address', true); return; }
    closeImagePicker(); setImageSrc(id, u);
  }
  $('ipUse').onclick = useUrl;
  $('ipUrl').addEventListener('keydown', function(k) { if (k.key === 'Enter') useUrl(); if (k.key === 'Escape') closeImagePicker(); });
  if ($('ipRemove')) $('ipRemove').onclick = function() { closeImagePicker(); setImageSrc(id, ''); };
}
document.addEventListener('pointerdown', function(ev) {
  var p = $('imgPicker'); if (p && !p.contains(ev.target) && !ev.target.closest('.img-btn')) closeImagePicker();
});
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeImagePicker(); });
// A paste with a picture link or file while an image is selected sets it
document.addEventListener('paste', function(ev) {
  var e = elById(selId); if (!e || e.kind !== 'image' || /^(INPUT|TEXTAREA)$/.test((document.activeElement || {}).tagName || '')) return;
  var f = droppedFile(ev.clipboardData), l = droppedLink(ev.clipboardData);
  if (f) { ev.preventDefault(); closeImagePicker(); uploadImage(e.id, f); }
  else if (l) { ev.preventDefault(); closeImagePicker(); setImageSrc(e.id, l); }
});

// ── Canvas ────────────────────────────────────────────────────────────────────
function render() {
  if (!layout) return;
  var holder = $('cardHolder'), c = layout.card;
  holder.style.width = (c.w * PX * zoom) + 'px'; holder.style.height = (c.h * PX * zoom) + 'px';
  holder.innerHTML = renderCardHTML(layout, ROWS[rowIdx] || null, true, { card: rowIdx, row: rowIdx, side: side }) +
    '<button type="button" class="flip-btn' + (side === 'back' ? ' on' : '') + '" onclick="flipCard()" title="' + (side === 'back' ? 'Back of the card — click for the front' : 'Front of the card — click for the back') + '">' + FLIP_ICON +
    '<span>' + (side === 'back' ? 'Back' : 'Front') + '</span></button>';
  var card = holder.firstChild;
  card.style.transform = 'scale(' + zoom + ')';
  card.style.setProperty('--z', zoom * PX);
  $('rowInfo').textContent = ROWS.length ? 'Row ' + (rowIdx + 1) + ' of ' + ROWS.length : 'No rows';
  $('rowPrev').disabled = rowIdx <= 0; $('rowNext').disabled = rowIdx >= ROWS.length - 1;
  $('zoomInfo').textContent = Math.round(zoom * 100) + '%';
  $('undoBtn').disabled = !undoStack.length; $('redoBtn').disabled = !redoStack.length;
}
// Turn the card over, with a flip animation
var _flipping = false;
function flipCard() {
  if (!layout || _flipping) return;
  closeImagePicker();
  var holder = $('cardHolder'), reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  function swap() { side = side === 'back' ? 'front' : 'back'; selId = null; render(); renderAttrs(); }
  if (reduce || !holder.animate) { swap(); return; }
  _flipping = true;
  var out = holder.animate([{ transform: 'perspective(1400px) rotateY(0deg)' }, { transform: 'perspective(1400px) rotateY(90deg)' }], { duration: 170, easing: 'ease-in' });
  out.onfinish = function() {
    swap();
    var back = holder.animate([{ transform: 'perspective(1400px) rotateY(-90deg)' }, { transform: 'perspective(1400px) rotateY(0deg)' }], { duration: 210, easing: 'ease-out' });
    back.onfinish = function() { _flipping = false; };
  };
}
function stepRow(d) { rowIdx = clamp(rowIdx + d, 0, Math.max(0, ROWS.length - 1)); render(); renderData(); }
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
// Drop an element from the left panel
(function() {
  var wrap = $('canvasWrap'), holder = $('cardHolder');
  wrap.addEventListener('dragover', function(ev) { if (!layout) return; ev.preventDefault(); ev.dataTransfer.dropEffect = 'copy'; holder.classList.add('drop-target'); });
  wrap.addEventListener('dragleave', function(ev) { if (ev.target === wrap) holder.classList.remove('drop-target'); });
  wrap.addEventListener('drop', function(ev) {
    ev.preventDefault(); holder.classList.remove('drop-target');
    if (!layout) return;
    var d = ev.dataTransfer.getData('text/plain') || '', p = ptFromEvent(ev), c = layout.card;
    var file = droppedFile(ev.dataTransfer), link = file ? '' : droppedLink(ev.dataTransfer);
    if (file || link) {
      // Onto an image element: replace its picture. Anywhere else: a new image element there.
      var hit = document.elementFromPoint(ev.clientX, ev.clientY), node = hit && hit.closest('.el.k-image'), e = node && elById(node.dataset.id);
      if (!e) { addAt('add:image', clamp(p.x, 0, c.w), clamp(p.y, 0, c.h)); e = elById(selId); }
      if (file) uploadImage(e.id, file); else setImageSrc(e.id, link);
      return;
    }
    if (d.indexOf(':') < 0 || /^https?:/i.test(d)) return;
    addAt(d, clamp(p.x, 0, c.w), clamp(p.y, 0, c.h));
  });
})();

// Move / resize elements on the card
$('cardHolder').addEventListener('pointerdown', function(ev) {
  if (ev.target.closest('.img-btn')) return;
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
      if (handle.indexOf('w') >= 0) x1 = Math.min(sn(o.x + dx), x2 - 2);
      if (handle.indexOf('e') >= 0) x2 = Math.max(sn(o.x + o.w + dx), x1 + 2);
      if (handle.indexOf('n') >= 0) y1 = Math.min(sn(o.y + dy), y2 - 2);
      if (handle.indexOf('s') >= 0) y2 = Math.max(sn(o.y + o.h + dy), y1 + 2);
      if (m.shiftKey && e.kind === 'shape' && handle.length === 2) {   // Shift: keep proportions
        var k = Math.max((x2 - x1) / o.w, (y2 - y1) / o.h);
        if (handle.indexOf('w') >= 0) x1 = x2 - o.w * k; else x2 = x1 + o.w * k;
        if (handle.indexOf('n') >= 0) y1 = y2 - o.h * k; else y2 = y1 + o.h * k;
      }
      e.x = r1(x1); e.y = r1(y1); e.w = r1(x2 - x1); e.h = r1(y2 - y1);
    }
    var node = $('cardHolder').querySelector('.el[data-id="' + e.id + '"]');
    if (node) {
      node.style.left = e.x + 'pt'; node.style.top = e.y + 'pt'; node.style.width = e.w + 'pt'; node.style.height = e.h + 'pt';
      if (e.kind === 'shape' && handle) node.querySelector('.el-inner').innerHTML = shapeSVG(e);
    }
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
function elById(id) { return layout && curEls().filter(function(e) { return e.id === id; })[0]; }
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

function deleteSel() { var e = elById(selId); if (!e) return; snapshot(); var list = curEls(); list.splice(list.indexOf(e), 1); selId = null; changed(); }
function duplicateSel() {
  var e = elById(selId); if (!e) return;
  var c = JSON.parse(JSON.stringify(e)); c.x = r1(c.x + 8); c.y = r1(c.y + 8);
  if (c.kind === 'gen') c.seed = uid();   // a copy draws its own random values
  addElementAt(c);
}
function reorderSel(dir) {
  var e = elById(selId); if (!e) return; snapshot();
  var zs = curEls().map(function(x) { return x.z || 1; });
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
function setBackProp(key, val) { snapshot(); layout.back[key] = val; changed(); }
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
function txtInp(label, key, val, ph) {
  return '<div><span class="mini">' + label + '</span><input class="inp" value="' + esc(val || '') + '" placeholder="' + esc(ph || '') + '" onchange="setProp(\'' + key + '\',this.value)"></div>';
}
function seg(key, opts, cur, rerender) {
  return '<div class="seg">' + opts.map(function(o) {
    return '<button type="button" class="' + (o[0] === cur ? 'on' : '') + '" title="' + o[2] + '" onclick="setProp(\'' + key + '\',\'' + o[0] + '\');renderAttrs()">' + o[1] + '</button>';
  }).join('') + '</div>';
}
function textAttrs(e) {
  var h = '<span class="lbl">Font</span><select class="sel" onchange="setProp(\'font\',this.value)">' +
          FONTS.map(function(f) { return '<option value="' + f[0] + '"' + (f[0] === (e.font || 'DINRegular') ? ' selected' : '') + '>' + f[1] + '</option>'; }).join('') + '</select>';
  h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_size', 'Size (pt)', e.size || 12, 'setProp', 'size', .5) + numInp('a_lh', 'Line height', e.lh || 1.15, 'setProp', 'lh', .05) + '</div>';
  h += '<div style="margin-top:.45rem">' + colorRow('Colour', 'color', e.color, false, 'setProp') + '</div>';
  h += '<div class="row2" style="margin-top:.45rem"><div><span class="mini">Style</span><div class="seg">' +
       '<button type="button" class="' + (e.bold ? 'on' : '') + '" onclick="setProp(\'bold\',' + !e.bold + ');renderAttrs()" title="Bold"><b>B</b></button>' +
       '<button type="button" class="' + (e.italic ? 'on' : '') + '" onclick="setProp(\'italic\',' + !e.italic + ');renderAttrs()" title="Italic"><i>I</i></button>' +
       '<button type="button" class="' + (e.upper ? 'on' : '') + '" onclick="setProp(\'upper\',' + !e.upper + ');renderAttrs()" title="Uppercase">AA</button></div></div>' +
       '<div><span class="mini">Align</span>' + seg('align', [['left', '⇤', 'Left'], ['center', '↔', 'Centre'], ['right', '⇥', 'Right']], e.align || 'left') + '</div></div>';
  h += '<div style="margin-top:.45rem"><span class="mini">Vertical</span>' + seg('valign', [['top', 'Top', 'Top'], ['middle', 'Middle', 'Middle'], ['bottom', 'Bottom', 'Bottom']], e.valign || 'top') + '</div>';
  return h;
}
function boxAttrs(e, title) {
  var h = '<span class="lbl">' + title + '</span>' + colorRow('Fill', 'bg', e.bg, true, 'setProp');
  h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_bw', 'Border (pt)', e.borderW, 'setProp', 'borderW', .5) + numInp('a_r', 'Corner radius', e.radius, 'setProp', 'radius') + '</div>';
  h += '<div style="margin-top:.45rem">' + colorRow('Border colour', 'borderColor', e.borderColor, false, 'setProp') + '</div>';
  if (isTextLike(e)) h += '<div style="margin-top:.45rem">' + numInp('a_pad', 'Padding (pt)', e.pad == null ? 2 : e.pad, 'setProp', 'pad', .5) + '</div>';
  return h;
}
function fitAttrs(e) {
  return '<div style="margin-top:.45rem"><span class="mini">Fit</span>' + seg('fit', [['cover', 'Fill', 'Fill the box (crops)'], ['contain', 'Fit', 'Show the whole image'], ['stretch', 'Stretch', 'Stretch to the box']], e.fit || 'cover') + '</div>';
}
function renderAttrs() {
  var p = $('attrPanel'); if (!layout) { p.innerHTML = ''; return; }
  var e = elById(selId), h = '';
  if (!e) {
    var c = layout.card;
    h += '<p class="attr-title">' + (side === 'back' ? 'Card back' : 'Card') + '</p><p class="muted" style="margin:0 0 .4rem">Select something on the card to change it. ' +
         (side === 'back' ? 'Flip the card (top right) to work on the front.' : 'Flip the card (top right) to design its back.') + '</p>';
    if (side === 'back') h += '<span class="lbl">Back</span>' + colorRow('Background', 'bg', layout.back.bg, false, 'setBackProp') +
      '<p class="muted" style="margin:.3rem 0 0">' + (backVaries() ? 'This back changes from card to card, so exports include a back for every card.' : 'Every card shares this back.') + '</p>';
    h += '<span class="lbl">Size</span><select class="sel" onchange="setCard(\'size\',this.value)">' +
         CARD_SIZES.map(function(s) { return '<option value="' + s.id + '"' + (s.id === (c.size || 'custom') ? ' selected' : '') + '>' + s.name + '</option>'; }).join('') + '</select>';
    h += '<div class="row2" style="margin-top:.45rem">' +
         '<div><span class="mini">Width (in)</span><input class="inp" type="number" step="0.05" value="' + Math.round(c.w / 72 * 100) / 100 + '" onchange="setCard(\'w\',this.value*72,true)"></div>' +
         '<div><span class="mini">Height (in)</span><input class="inp" type="number" step="0.05" value="' + Math.round(c.h / 72 * 100) / 100 + '" onchange="setCard(\'h\',this.value*72,true)"></div></div>';
    h += '<span class="lbl">' + (side === 'back' ? 'Both sides' : 'Look') + '</span>' + (side === 'back' ? '' : colorRow('Background', 'bg', c.bg, false, 'setCard'));
    h += '<div class="row2" style="margin-top:.45rem">' + numInp('c_r', 'Corner radius (pt)', c.radius, 'setCard', 'radius') + numInp('c_bw', 'Border (pt)', c.borderW, 'setCard', 'borderW', .5) + '</div>';
    h += '<div style="margin-top:.45rem">' + colorRow('Border colour', 'borderColor', c.borderColor, false, 'setCard') + '</div>';
    h += '<span class="lbl">Shortcuts</span><p class="muted" style="margin:0">Arrows nudge 1 pt (Shift: 10) · Alt while dragging: fine moves · Shift+corner: keep a shape\'s proportions · ⌘D duplicate · Delete removes · ⌘Z undo · ⌘S save</p>';
    p.innerHTML = h; return;
  }
  var title = e.kind === 'data' ? e.field : e.kind === 'shape' ? (SHAPES.filter(function(s) { return s[0] === e.shape; })[0] || ['', 'Shape'])[1]
            : e.kind === 'gen' ? (GENS.filter(function(g) { return g[0] === e.mode; })[0] || ['', '', 'Generated'])[2] : KIND_NAME[e.kind];
  h += '<p class="attr-title">' + esc(title) + ' <span class="kind">' + KIND_NAME[e.kind] + '</span></p>';

  if (e.kind === 'text') {
    h += '<span class="lbl">Text</span><textarea class="ta" id="a_text" oninput="liveText(this.value)" onchange="setProp(\'text\',this.value)">' + esc(e.text) + '</textarea>';
    h += '<div class="insert-row"><select class="sel" id="a_ins"><option value="">Insert a column…</option>' + FIELDS.map(function(f) { return '<option>' + esc(f) + '</option>'; }).join('') + '</select>' +
         '<button class="attr-btn" style="flex:0 0 auto;padding:0 .7rem" onclick="insertField()">Insert</button></div>';
    h += textAttrs(e) + boxAttrs(e, 'Background');
  }
  if (e.kind === 'data') {
    var known = FIELDS.indexOf(e.field) >= 0;
    h += '<span class="lbl">Column</span><select class="sel" onchange="setProp(\'field\',this.value);renderAttrs()">' +
         FIELDS.map(function(f) { return '<option' + (f === e.field ? ' selected' : '') + '>' + esc(f) + '</option>'; }).join('') +
         (known ? '' : '<option selected>' + esc(e.field) + '</option>') + '</select>';
    h += '<div style="margin-top:.45rem"><span class="mini">Show as</span>' + seg('as', [['text', 'Text', 'Show the value as text'], ['image', 'Image', 'The value is a picture link']], e.as || 'text') + '</div>';
    h += e.as === 'image' ? fitAttrs(e) + boxAttrs(e, 'Frame') : textAttrs(e) + boxAttrs(e, 'Background');
  }
  if (e.kind === 'image') {
    var u = imageUrl(e.src), src = e.src || '', uploaded = /\/images\/[A-Za-z0-9_\-]+\/[0-9a-f]{20}\.\w+$/.test(src);
    var from = !src ? '' : uploaded ? 'Uploaded file' : /dropbox\.com/i.test(src) ? 'Dropbox link' : /google\.com/i.test(src) ? 'Google Drive link' : 'Web link';
    h += '<span class="lbl">Picture</span><div class="img-slot' + (UPLOADING[e.id] ? ' img-loading' : '') + '" id="imgSlot" onclick="openImagePicker(event,\'' + e.id + '\')" title="Click or drop a picture to change it">' +
         (u ? '<div class="el-img" style="background-image:url(&quot;' + esc(u) + '&quot;);background-size:contain"></div>' : '<div class="img-slot-empty">' + IMG_ICON + '<span>Drop a picture, or click to choose</span></div>') +
         '<span class="img-btn">' + IMG_ICON + '</span></div>';
    if (from) h += '<p class="muted" style="margin:.3rem 0 0">' + from + (uploaded ? '' : ' — loaded from the source, so changes there show up here') + '</p>';
    h += '<p class="muted" style="margin:.3rem 0 0">The same picture on every card — a logo, frame or icon. For a different picture per card, use a Data column and set it to Image.</p>';
    h += fitAttrs(e) + boxAttrs(e, 'Frame');
  }
  if (e.kind === 'shape') {
    h += '<span class="lbl">Shape</span><select class="sel" onchange="setProp(\'shape\',this.value);renderAttrs()">' +
         SHAPES.map(function(s) { return '<option value="' + s[0] + '"' + (s[0] === e.shape ? ' selected' : '') + '>' + s[1] + '</option>'; }).join('') + '</select>';
    if (e.shape !== 'line') h += '<div style="margin-top:.45rem">' + colorRow('Fill', 'fill', e.fill, true, 'setProp') + '</div>';
    h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_sw', e.shape === 'line' ? 'Thickness (pt)' : 'Outline (pt)', e.strokeW, 'setProp', 'strokeW', .5) +
         (/^(rect|round)$/.test(e.shape) ? numInp('a_r', 'Corner radius', e.shape === 'round' && e.radius == null ? 10 : e.radius, 'setProp', 'radius') : '<div></div>') + '</div>';
    h += '<div style="margin-top:.45rem">' + colorRow(e.shape === 'line' ? 'Colour' : 'Outline colour', 'stroke', e.stroke, false, 'setProp') + '</div>';
  }
  if (e.kind === 'gen') {
    h += '<span class="lbl">Generate</span>' + seg('mode', [['seq', '123', 'Number sequence'], ['rand', '🎲', 'Random number'], ['list', '☰', 'Random from a list']], e.mode);
    if (e.mode === 'seq') {
      h += '<div class="row2" style="margin-top:.45rem">' + numInp('g_start', 'Start at', e.start == null ? 1 : e.start, 'setProp', 'start') + numInp('g_step', 'Step', e.step == null ? 1 : e.step, 'setProp', 'step') + '</div>';
      h += '<div class="row2" style="margin-top:.45rem">' + numInp('g_pad', 'Digits (pad with 0)', e.pad || 0, 'setProp', 'pad') + '<div></div></div>';
      h += '<div class="row2" style="margin-top:.45rem">' + txtInp('Before', 'prefix', e.prefix, '#') + txtInp('After', 'suffix', e.suffix, '') + '</div>';
    }
    if (e.mode === 'rand') h += '<div class="row2" style="margin-top:.45rem">' + numInp('g_min', 'From', e.min == null ? 1 : e.min, 'setProp', 'min') + numInp('g_max', 'To', e.max == null ? 6 : e.max, 'setProp', 'max') + '</div>';
    if (e.mode === 'list') {
      h += '<span class="mini" style="margin-top:.45rem">Values (one per line)</span><textarea class="ta" onchange="setProp(\'items\',this.value)">' + esc(e.items || '') + '</textarea>';
      h += '<div style="margin-top:.45rem"><span class="mini">Pick</span>' + seg('pick', [['random', 'Random', 'A random value for each card'], ['cycle', 'In order', 'Go through the list in order']], e.pick || 'random') + '</div>';
    }
    h += '<div style="margin-top:.45rem"><span class="mini">Counts</span>' + seg('per', [['card', 'Each card printed', 'Copies of a row get their own number'], ['row', 'Each row', 'Copies of a row share the same value']], e.per || 'card') + '</div>';
    if (e.mode !== 'seq') h += '<div class="attr-btns"><button class="attr-btn" onclick="setProp(\'seed\',uid())">🎲 Re-roll</button></div>';
    h += textAttrs(e) + boxAttrs(e, 'Background');
  }
  h += '<span class="lbl">Position (pt)</span><div class="row4">' + numInp('a_x', 'X', e.x, 'setProp', 'x', .5) + numInp('a_y', 'Y', e.y, 'setProp', 'y', .5) + numInp('a_w', 'W', e.w, 'setProp', 'w', .5) + numInp('a_h', 'H', e.h, 'setProp', 'h', .5) + '</div>';
  h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_rot', 'Rotate (°)', e.rot || 0, 'setProp', 'rot', 1) +
       '<div><span class="mini">Opacity</span><input class="inp" type="number" min="0" max="100" step="5" value="' + Math.round((e.opacity == null ? 1 : e.opacity) * 100) + '" onchange="setProp(\'opacity\',clamp(this.value/100,0,1),true)"></div></div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="centerSel(\'x\')">Centre ↔</button><button class="attr-btn" onclick="centerSel(\'y\')">Centre ↕</button></div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="reorderSel(1)">To front</button><button class="attr-btn" onclick="reorderSel(-1)">To back</button></div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="duplicateSel()">Duplicate</button><button class="attr-btn danger" onclick="deleteSel()">Delete</button></div>';
  p.innerHTML = h;
  var slot = $('imgSlot');
  if (slot) {
    slot.addEventListener('dragover', function(d) { d.preventDefault(); slot.classList.add('over'); });
    slot.addEventListener('dragleave', function() { slot.classList.remove('over'); });
    slot.addEventListener('drop', function(d) {
      d.preventDefault(); slot.classList.remove('over');
      var f = droppedFile(d.dataTransfer), l = droppedLink(d.dataTransfer);
      if (f) uploadImage(e.id, f); else if (l) setImageSrc(e.id, l);
    });
  }
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
// Copies: 1 each, or a numeric column (Count, Qty…). Fills the select and returns [{row, ri}].
function cardList(cs, onChange) {
  var cur = cs.value;
  var numeric = FIELDS.filter(function(f) { return ROWS.length && ROWS.every(function(r) { var v = cleanVal(r[f]).trim(); return v === '' || /^\d{1,3}$/.test(v); }); });
  cs.innerHTML = '<option value="">1 of each card</option>' + numeric.map(function(f) { return '<option value="' + esc(f) + '">From "' + esc(f) + '"</option>'; }).join('');
  if (!cur && !cs.dataset.touched) cur = numeric.filter(function(f) { return /^(count|qty|quantity|copies|#)$/i.test(f); })[0] || '';
  cs.value = numeric.indexOf(cur) >= 0 ? cur : '';
  cs.onchange = function() { cs.dataset.touched = '1'; onChange(); };
  var list = [];
  ROWS.forEach(function(r, ri) {
    var n = cs.value ? parseInt(cleanVal(r[cs.value]), 10) : 1;
    if (isNaN(n)) n = 0;
    for (var i = 0; i < n; i++) list.push({ row: r, ri: ri });
  });
  return list;
}
var PAPER = { letter: [612, 792], a4: [595.3, 841.9] };
function renderPrint() {
  var pages = $('pages');
  if (!layout) { pages.innerHTML = ''; return; }
  var list = cardList($('pCopies'), renderPrint);

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
  // Backs: none, or a back page after each front page laid out so it lines up when printed double-sided
  var backs = $('pBacks').value;
  $('printInfo').textContent = list.length + (list.length === 1 ? ' card' : ' cards') + ' · ' + per + ' per page · ' +
    (backs === 'none' ? nPages + (nPages === 1 ? ' page' : ' pages') : nPages * 2 + ' pages (fronts and backs)');
  for (var p = 0; p < nPages; p++) {
    var cards = list.slice(p * per, (p + 1) * per);
    [false, true].forEach(function(isBack) {
      if (isBack && backs === 'none') return;
      html += '<div class="page-holder" style="width:' + (pw * PX * scale) + 'px;height:' + (ph * PX * scale) + 'px">' +
              (backs !== 'none' ? '<span class="page-tag">' + (isBack ? 'Back' : 'Front') + ' · page ' + (p + 1) + '</span>' : '') +
              '<div class="page" style="width:' + pw + 'pt;height:' + ph + 'pt;transform:scale(' + scale + ')">';
      cards.forEach(function(it, i) {
        var col = i % cols, row = Math.floor(i / cols);
        if (isBack && backs === 'long') col = cols - 1 - col;   // flipped over the long edge: columns swap
        if (isBack && backs === 'short') row = rowsN - 1 - row; // flipped over the short edge: rows swap
        var cx = ox + col * (c.w + gap), cy = oy + row * (c.h + gap);
        html += '<div style="position:absolute;left:' + cx + 'pt;top:' + cy + 'pt;width:' + c.w + 'pt;height:' + c.h + 'pt' + (cut === 'outline' ? ';outline:.5pt solid #9aa0b8' : '') + '">' +
                renderCardHTML(layout, it.row, false, { card: p * per + i, row: it.ri, side: isBack ? 'back' : 'front' }) + '</div>';
      });
      if (!isBack) html += cropMarks(cards.length);
      html += '</div></div>';
    });
  }
  function cropMarks(count) {
    if (cut !== 'marks' || !count) return '';
    var out = '', used = Math.ceil(count / cols), len = 10, xs = [], ys = [];
    for (var ci = 0; ci < Math.min(cols, count); ci++) { xs.push(ox + ci * (c.w + gap)); xs.push(ox + ci * (c.w + gap) + c.w); }
    for (var ri = 0; ri < used; ri++) { ys.push(oy + ri * (c.h + gap)); ys.push(oy + ri * (c.h + gap) + c.h); }
    var top = ys[0], bottom = ys[ys.length - 1], left = xs[0], right = xs[xs.length - 1];
    xs.forEach(function(x) {
      out += '<div class="cut" style="left:' + (x - .25) + 'pt;top:' + (top - len - 2) + 'pt;width:.5pt;height:' + len + 'pt"></div>';
      out += '<div class="cut" style="left:' + (x - .25) + 'pt;top:' + (bottom + 2) + 'pt;width:.5pt;height:' + len + 'pt"></div>';
    });
    ys.forEach(function(y) {
      out += '<div class="cut" style="top:' + (y - .25) + 'pt;left:' + (left - len - 2) + 'pt;height:.5pt;width:' + len + 'pt"></div>';
      out += '<div class="cut" style="top:' + (y - .25) + 'pt;left:' + (right + 2) + 'pt;height:.5pt;width:' + len + 'pt"></div>';
    });
    return out;
  }
  pages.innerHTML = list.length ? html : '<div class="muted">No cards to print.</div>';
}

// ── Export: card sheets for Tabletop Simulator and Screentop ──────────────────
// Cards are drawn in the browser (html-to-image), so pictures from other sites
// pass through push/cardboardImage.php, fetched fresh and never stored.
var EXPORT_TARGETS = {
  tts:       { name: 'Tabletop Simulator', maxCols: 10, maxRows: 7, backLast: true },
  screentop: { name: 'Screentop',          maxCols: 0,  maxRows: 0, backLast: true },
  images:    { name: 'Separate images',    single: true }
};
var _libs = null, _exporting = false;
function loadScript(src) {
  return new Promise(function(ok, bad) { var t = document.createElement('script'); t.src = src; t.onload = ok; t.onerror = function() { bad(new Error('Couldn\'t load ' + src.split('/').pop())); }; document.head.appendChild(t); });
}
function exportLibs() {
  return _libs || (_libs = Promise.all([
    window.htmlToImage ? 0 : loadScript('https://cdn.jsdelivr.net/npm/html-to-image@1.11.13/dist/html-to-image.js'),
    window.JSZip ? 0 : loadScript('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js')
  ]).catch(function(e) { _libs = null; throw e; }));
}
function proxied(u) {
  if (/^data:/.test(u)) return u;
  try { if (new URL(u, location.href).origin === location.origin) return u; } catch (e) { return u; }
  return APP_BASE + 'push/cardboardImage.php?id=' + encodeURIComponent(SHEET_ID) + '&url=' + encodeURIComponent(u);
}
function backHTML(it, i, ctx) {
  return renderCardHTML(ctx && ctx.lay || layout, it ? it.row : (ROWS[0] || null), false, Object.assign({ side: 'back', card: i || 0, row: it ? it.ri : 0 }, ctx || {}));
}

// How the cards fall onto sheets for the chosen target
function exportPlan() {
  var t = EXPORT_TARGETS[$('eTarget').value] || EXPORT_TARGETS.tts, c = layout.card;
  var list = cardList($('eCopies'), renderExport), n = list.length;
  var maxPx = +$('eQuality').value || 4096, round = $('eCorners').checked;
  if (t.single) {
    var w1 = Math.round(Math.min(maxPx / 4, 1500));
    return { t: t, list: list, single: true, cardW: w1, cardH: Math.round(w1 * c.h / c.w), round: round, sheets: [] };
  }
  var slots = n + (t.backLast ? 1 : 0), cols, rows, cardW;
  if (t.maxCols) {               // TTS: up to 10 × 7 per sheet, the last slot is the hidden-card image
    cols = Math.max(2, Math.min(t.maxCols, slots)); rows = Math.max(2, Math.min(t.maxRows, Math.ceil(slots / cols)));
  } else {                       // Screentop: as square a grid as fits the size limit
    var ratio = c.h / c.w; cols = Math.max(1, Math.ceil(Math.sqrt(slots * ratio))); rows = Math.ceil(slots / cols);
    while (cols * rows < slots) rows++;
    var minW = 150;              // keep cards legible; overflow goes onto more sheets
    var fitCols = Math.floor(maxPx / minW), fitRows = Math.floor(maxPx / (minW * ratio));
    cols = Math.min(cols, fitCols); rows = Math.min(rows, fitRows);
  }
  cardW = Math.floor(Math.min(maxPx / cols, maxPx / rows * c.w / c.h));
  var per = cols * rows - (t.backLast ? 1 : 0), sheets = [];
  for (var i = 0; i < Math.max(1, Math.ceil(n / per)); i++) sheets.push(list.slice(i * per, (i + 1) * per));
  return { t: t, list: list, cols: cols, rows: rows, per: per, cardW: cardW, cardH: Math.round(cardW * c.h / c.w), sheets: sheets, round: round };
}

function renderExport() {
  var box = $('exportPreview'); if (!layout) { box.innerHTML = ''; return; }
  var P = exportPlan(), c = layout.card, varies = backVaries();
  $('eBackNote').textContent = varies ? 'Each card has its own back' : 'All cards share one back';
  var info;
  if (P.single) info = P.list.length + ' cards → ' + P.list.length + ' images + back, ' + P.cardW + ' × ' + P.cardH + ' px each';
  else info = P.list.length + ' cards → ' + P.sheets.length + (P.sheets.length === 1 ? ' sheet' : ' sheets') + ' of ' + P.cols + ' × ' + P.rows +
             ' · ' + (P.cols * P.cardW) + ' × ' + (P.rows * P.cardH) + ' px · ' + P.cardW + ' × ' + P.cardH + ' px per card';
  $('exportInfo').textContent = info;
  $('exportHelp').innerHTML = exportHelp(P);
  // Preview (HTML, scaled down) — the download draws the real pictures
  var h = '', cw = c.w * PX, chh = c.h * PX, flat = P.round ? c : Object.assign({}, c, { radius: 0 });
  var lay = P.round ? layout : Object.assign({}, layout, { card: flat });
  function cell(inner) { return '<div class="x-cell" style="width:' + cw + 'px;height:' + chh + 'px">' + inner + '</div>'; }
  if (P.single) {
    var sc1 = 110 / cw;
    h += '<div class="x-sheet x-flow">' + P.list.slice(0, 60).map(function(it, i) {
      return '<div class="x-thumb" style="width:' + cw * sc1 + 'px;height:' + chh * sc1 + 'px"><div style="transform:scale(' + sc1 + ');transform-origin:0 0">' + cell(renderCardHTML(lay, it.row, false, { card: i, row: it.ri })) + '</div></div>';
    }).join('') + (P.list.length > 60 ? '<div class="muted">+ ' + (P.list.length - 60) + ' more</div>' : '') +
         '<div class="x-thumb x-back" style="width:' + cw * sc1 + 'px;height:' + chh * sc1 + 'px"><div style="transform:scale(' + sc1 + ');transform-origin:0 0">' + cell(backHTML(P.list[0], 0, { lay: lay })) + '</div></div></div>';
  } else {
    var sw = P.cols * cw, sh = P.rows * chh, sc = Math.min(1, (box.clientWidth - 60) / sw, 760 / sh) || .3, start = 0;
    P.sheets.forEach(function(cards, si) {
      h += '<div class="x-label">Sheet ' + (si + 1) + ' · ' + cards.length + ' cards</div>';
      h += '<div class="x-sheet" style="width:' + sw * sc + 'px;height:' + sh * sc + 'px"><div class="x-grid" style="width:' + sw + 'px;grid-template-columns:repeat(' + P.cols + ',' + cw + 'px);transform:scale(' + sc + ')">';
      cards.forEach(function(it, i) { h += cell(renderCardHTML(lay, it.row, false, { card: start + i, row: it.ri })); });
      for (var k = cards.length; k < P.cols * P.rows - (P.t.backLast ? 1 : 0); k++) h += cell('');
      if (P.t.backLast) h += '<div class="x-cell x-back" title="Card back — shown for hidden cards" style="width:' + cw + 'px;height:' + chh + 'px">' + backHTML(cards[0], start, { lay: lay }) + '</div>';
      h += '</div></div>';
      start += cards.length;
    });
  }
  box.innerHTML = P.list.length ? h : '<div class="muted">No cards to export.</div>';
}
function exportHelp(P) {
  var tgt = $('eTarget').value;
  var uniq = backVaries();
  if (tgt === 'tts') return '<b>In Tabletop Simulator:</b> Objects → Components → Custom → <b>Deck</b>. For each sheet: Face = <code>faces-N.png</code>, ' +
    (uniq ? 'tick <b>Unique backs</b> and set Back = <code>backs-N.png</code>, ' : 'Back = <code>back.png</code> (Unique backs off), ') +
    'Width <b>' + P.cols + '</b>, Height <b>' + P.rows + '</b>, Number = the cards on that sheet (shown above each one). Tick <b>Back is hidden</b>. ' +
    'The last slot on each sheet is the back, which TTS shows for cards in other players\' hands.';
  if (tgt === 'screentop') return '<b>In Screentop:</b> add each <code>sheet-N.png</code>' + (uniq ? ' (and its <code>backs-N.png</code>)' : '') + ' as an image and split it into a grid of <b>' + P.cols + '</b> columns × <b>' + P.rows + '</b> rows. ' +
    'The last cell is the card back. Sheets stay under 4096 px, Screentop\'s image limit.';
  return 'One picture per card (<code>card-001.png</code> …) plus ' + (uniq ? 'its back (<code>card-001-back.png</code> …)' : '<code>back.png</code>') + ' — for Screentop\'s one-image-per-card decks, or anywhere else.';
}

// Draw one card (or the back) into a canvas at w × h px
var _fontCSS = null;
function drawCard(html, w, h) {
  var c = layout.card, holder = $('exportStage');
  holder.innerHTML = html;
  var node = holder.firstChild;
  node.style.boxShadow = 'none'; node.style.transform = 'none';
  var opts = { width: c.w * PX, height: c.h * PX, pixelRatio: w / (c.w * PX), cacheBust: false,   // vector-scaled, so it stays sharp
               imagePlaceholder: 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' };
  var fp = _fontCSS ? Promise.resolve(_fontCSS) : fontSampler().then(function(css) { return (_fontCSS = css); }).catch(function() { return ''; });
  return fp.then(function(css) { opts.fontEmbedCSS = css; return htmlToImage.toCanvas(node, opts); });
}
// html-to-image only embeds fonts the sampled node uses, so sample every font once (all weights/styles)
function fontSampler() {
  var d = document.createElement('div');
  d.style.cssText = 'position:fixed;left:-20000px;top:0';
  d.innerHTML = FONTS.map(function(f) {
    return ['400', '700'].map(function(w) { return '<span style="font-family:\'' + f[0] + '\';font-weight:' + w + '">Aa</span><i style="font-family:\'' + f[0] + '\';font-weight:' + w + '">Aa</i>'; }).join('');
  }).join('');
  document.body.appendChild(d);
  return document.fonts.ready.then(function() { return htmlToImage.getFontEmbedCSS(d); }).then(function(css) { d.remove(); return css; }, function(e) { d.remove(); throw e; });
}
function canvasBlob(cv, type) { return new Promise(function(ok) { cv.toBlob(ok, type || 'image/png', .92); }); }

function runExport() {
  if (_exporting || !layout) return;
  var P = exportPlan(); if (!P.list.length) { toast('No cards to export', true); return; }
  var btn = $('exportGo'), bar = $('exportProgress'), total = P.list.length + 1, done = 0;
  var lay = P.round ? layout : Object.assign({}, layout, { card: Object.assign({}, layout.card, { radius: 0 }) });
  var keep = layout, uniq = backVaries();
  if (uniq) total += P.list.length;
  _exporting = true; btn.disabled = true; bar.style.display = 'block';
  function tick(msg) { bar.firstChild.style.width = Math.round(done / total * 100) + '%'; $('exportStatus').textContent = msg; }
  tick('Loading…');
  var zip, back;
  exportLibs()
    .then(function() {
      zip = new JSZip();
      layout = lay;   // drawn with the export's corner setting
      if (uniq) return;
      return drawCard(backHTML(P.list[0], 0, { lay: lay, proxy: true }), P.cardW, P.cardH).then(function(cv) { back = cv; return canvasBlob(cv); }).then(function(b) { zip.file('back.png', b); });
    })
    .then(function() {
      var chain = Promise.resolve(), index = 0;
      if (P.single) {
        P.list.forEach(function(it, i) {
          chain = chain.then(function() {
            tick('Drawing card ' + (i + 1) + ' of ' + P.list.length + '…');
            var nm = 'card-' + String(i + 1).padStart(3, '0');
            return drawCard(renderCardHTML(lay, it.row, false, { card: i, row: it.ri, proxy: true }), P.cardW, P.cardH)
              .then(canvasBlob).then(function(b) { zip.file(nm + '.png', b); done++; })
              .then(function() { if (uniq) return drawCard(backHTML(it, i, { lay: lay, proxy: true }), P.cardW, P.cardH).then(canvasBlob).then(function(b) { zip.file(nm + '-back.png', b); done++; }); });
          });
        });
        return chain;
      }
      var prefix = $('eTarget').value === 'tts' ? 'faces-' : 'sheet-';
      P.sheets.forEach(function(cards, si) {
        var sheet = document.createElement('canvas'), bsheet = document.createElement('canvas');
        chain = chain.then(function() {
          sheet.width = bsheet.width = P.cols * P.cardW; sheet.height = bsheet.height = P.rows * P.cardH;
          var g = sheet.getContext('2d'), bg = bsheet.getContext('2d'), hidden = null;
          var inner = Promise.resolve();
          cards.forEach(function(it, i) {
            var k = index++;
            inner = inner.then(function() {
              tick('Sheet ' + (si + 1) + ': card ' + (i + 1) + ' of ' + cards.length + '…');
              var x = (i % P.cols) * P.cardW, y = Math.floor(i / P.cols) * P.cardH;
              return drawCard(renderCardHTML(lay, it.row, false, { card: k, row: it.ri, proxy: true }), P.cardW, P.cardH)
                .then(function(cv) { g.drawImage(cv, x, y); done++; })
                .then(function() { if (uniq) return drawCard(backHTML(it, k, { lay: lay, proxy: true }), P.cardW, P.cardH).then(function(cv) { bg.drawImage(cv, x, y); if (!hidden) hidden = cv; done++; }); });
            });
          });
          return inner.then(function() {
            var hx = (P.cols - 1) * P.cardW, hy = (P.rows - 1) * P.cardH;
            if (P.t.backLast) { g.drawImage(uniq ? hidden : back, hx, hy); if (uniq) bg.drawImage(hidden, hx, hy); }
            return canvasBlob(sheet);
          }).then(function(b) {
            zip.file(prefix + (si + 1) + '.png', b);
            if (uniq) return canvasBlob(bsheet).then(function(b2) { zip.file('backs-' + (si + 1) + '.png', b2); });
          });
        });
      });
      return chain;
    })
    .then(function() {
      done = total; tick('Packing…');
      zip.file('README.txt', exportHelp(P).replace(/<[^>]+>/g, '') + '\n\nMade with CardBoard — zapsheets.com\n');
      return zip.generateAsync({ type: 'blob' });
    })
    .then(function(blob) {
      var a = document.createElement('a'), name = (curTab || 'cards').replace(/[^\w\- ]+/g, '').trim() || 'cards';
      a.href = URL.createObjectURL(blob); a.download = name + ' - ' + EXPORT_TARGETS[$('eTarget').value].name + '.zip';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function() { URL.revokeObjectURL(a.href); }, 60000);
      toast('✓ Exported ' + P.list.length + ' cards');
    })
    .catch(function(e) { toast('Export failed — ' + e.message, true); })
    .then(function() {
      layout = keep; _exporting = false; btn.disabled = false; bar.style.display = 'none'; $('exportStage').innerHTML = ''; $('exportStatus').textContent = '';
    });
}

// ── Tabs, menu, donation ──────────────────────────────────────────────────────
function switchTab(t) {
  _activeTab = t;
  ['design', 'print', 'export'].forEach(function(k) {
    var cap = k.charAt(0).toUpperCase() + k.slice(1);
    $('tab' + cap).classList.toggle('active', t === k); $('view-' + k).classList.toggle('active', t === k);
  });
  if (t === 'print') renderPrint(); else if (t === 'export') renderExport(); else fitZoom();
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
