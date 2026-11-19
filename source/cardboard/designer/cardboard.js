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
var multi = [];   // ids when more than one element is selected (selId is then null)
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
var DATA_ICON = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><ellipse cx="12" cy="5.5" rx="7.5" ry="2.8"/><path d="M4.5 5.5v13c0 1.55 3.36 2.8 7.5 2.8s7.5-1.25 7.5-2.8v-13"/><path d="M4.5 12c0 1.55 3.36 2.8 7.5 2.8s7.5-1.25 7.5-2.8"/></svg>';   // a sheet column (Data field)
var DIE_ICON = '<svg class="die-ico" viewBox="0 0 24 24" width="1.05em" height="1.05em" aria-hidden="true" style="vertical-align:-.15em"><rect x="3" y="3" width="18" height="18" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><g fill="currentColor"><circle cx="8" cy="8" r="1.6"/><circle cx="16" cy="8" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="8" cy="16" r="1.6"/><circle cx="16" cy="16" r="1.6"/></g></svg>';
var GENS = [['seq', '123', 'Number sequence'], ['rand', DIE_ICON, 'Random number'], ['list', '☰', 'Random from list']];
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
  return (lay || layout).back.els.some(function(e) { return e.kind === 'data' || e.kind === 'gen' || (e.kind === 'text' && /\{[^{}]+\}/.test(e.text || '')) || (e.kind === 'image' && e.mapOn && e.map && e.map.field); });
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
  if (!Array.isArray(lay.groups)) lay.groups = [];
  if (!Array.isArray(lay.back.groups)) lay.back.groups = [];
  if (!Array.isArray(lay.hidden)) lay.hidden = [];
  lay.v = 3;
  return lay;
}

// ── Server ────────────────────────────────────────────────────────────────────
function api(action, extra) {
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('action', action);
  Object.keys(extra || {}).forEach(function(k) { fd.append(k, extra[k]); });
  return cbPost(APP_BASE + 'push/cardboardData.php', fd)
    .then(function(res) { if (!res || res.error) throw new Error(res && res.error ? res.error : 'No response'); return res; });
}
// POST with the account token. A stale sign-in is refreshed once and the request sent again;
// no sign-in asks for one; a finished free week locks the designer.
function cbPost(url, fd, again) {
  if (ACCT && ACCT.token) fd.set('cbt', ACCT.token);
  return fetch(url, { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(res) {
    if (res && res.need_auth && !again) {
      var next = res.refresh && ACCT ? acctRefresh(true) : askSignIn();
      return next.then(function() { return cbPost(url, fd, true); }, function() { return res; });
    }
    if (res && res.locked) { acctRefresh(true).then(applyAcct, applyAcct); if (!again) openPlansDialog('locked'); }
    return res;
  });
}
function toast(msg, err) {
  var t = $('toast'); t.textContent = msg; t.className = 'toast show' + (err ? ' err' : '');
  clearTimeout(t._h); t._h = setTimeout(function() { t.className = 'toast' + (err ? ' err' : ''); }, err ? 4200 : 1800);
}

// ── Values from the sheet ─────────────────────────────────────────────────────
// Keys the app adds to each row for itself (not columns of the sheet). Columns may start with _ (like _Action).
function isInternal(k) { return k === '_row' || k === '_notes'; }
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
  var img = '<img class="el-img" src="' + esc(u) + '" alt="" draggable="false" referrerpolicy="no-referrer" style="object-fit:' + fit + '"';
  if (!(ctx && ctx.designing)) return img + '>';
  // In the designer, a spinner sits over the picture until it has loaded (or failed)
  return img + ' onload="var s=this.nextElementSibling;if(s)s.remove()" onerror="var s=this.nextElementSibling;if(s)s.className=\'img-spin failed\'"><span class="img-spin"></span>';
}
function elInner(e, row, ctx) {
  ctx = ctx || { card: 0, row: 0 };
  switch (e.kind) {
    case 'text': {
      var t = fill(e.text, row);
      return t.trim() ? rich(t, ctx) : (row ? '' : '<span class="el-empty">' + esc(e.text || 'Text') + '</span>');
    }
    case 'data':
      if (e.as === 'image') return imgDiv(row ? imageUrl(row[e.field]) : '', e, ctx.designing ? e.field : '', ctx);
      return row ? rich(textVal(row[e.field]), ctx) : '<span class="el-empty">' + esc(e.field) + '</span>';
    case 'image': { var iu = imageUrl(imgSrcFor(e, row)); return iu || ctx && ctx.designing ? imgDiv(iu, e, 'Drop an image here, or double-click to choose', ctx) : ''; }
    case 'shape': return shapeSVG(e);
    case 'gen':   return rich(genValue(e, ctx), ctx);
  }
  return '';
}
function renderCardHTML(lay, row, designing, ctx) {
  ctx = Object.assign({ card: 0, row: 0 }, ctx || {}, { designing: !!designing });
  var face = faceOf(lay, ctx.side), cs = ctx.side === 'back' ? Object.assign({}, lay.card, { bg: lay.back.bg }) : lay.card;
  var h = '<div class="card' + (designing ? ' designing' : '') + (ctx.side === 'back' ? ' back' : '') + '" style="' + cardStyle(cs) + '">';
  face.els.slice().sort(function(a, b) { return (a.z || 1) - (b.z || 1); }).forEach(function(e) {
    var cls = 'el k-' + e.kind + (e.kind === 'data' && e.as === 'image' ? ' as-image' : '') + (e.valign === 'middle' ? ' v-middle' : e.valign === 'bottom' ? ' v-bottom' : '') + (designing && isSel(e.id) ? ' picked' + (multi.length ? ' multi' : '') : '');
    if (designing && UPLOADING[e.id]) cls += ' img-loading';
    h += '<div class="' + cls + '" data-id="' + e.id + '" style="' + elStyle(e) + '">';
    h += designing
      ? '<div class="el-inner">' + elInner(e, row, ctx) + '</div>' +
        ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].map(function(d) { return '<span class="hdl ' + d + '" data-h="' + d + '"></span>'; }).join('') +
        '<span class="hdl rot" data-h="rot" title="Drag to rotate · Shift: 15° steps"></span>'
      : elInner(e, row, ctx);
    h += '</div>';
  });
  if (designing && multi.length) {   // one box around everything selected (a group or several elements)
    var bb = selBox(face.els.filter(function(x) { return multi.indexOf(x.id) >= 0; }));
    if (bb) h += '<div class="sel-box' + (selGroupId() ? ' group' : '') + '" style="left:' + bb.x + 'pt;top:' + bb.y + 'pt;width:' + bb.w + 'pt;height:' + bb.h + 'pt"></div>';
  }
  if (designing && !face.els.length) h += '<div class="card-empty-hint">' + (ctx.side === 'back' ? 'The back of the card — drag elements here' : 'Drag elements here') + '</div>';
  return h + '</div>';
}

// ── Loading ───────────────────────────────────────────────────────────────────
// Nothing is read from the spreadsheet until the person has signed in (or started their free week)
function init() {
  applyAcct();
  if (!ACCT) {
    $('cardHolder').innerHTML = '';
    askSignIn(true).then(function() { init(); }, function() {});
    return;
  }
  acctRefresh(!hasAccess()).then(function() {}, function() {});
  renderPalette();
  $('cardHolder').innerHTML = '<div class="loading" style="width:240px;height:200px"><span class="spin"></span>Loading sheet…</div>';
  loadTags();
  // One quick call: the tabs, the layouts and the first rows of the tab to show. The rest of
  // that tab's rows load in the background. (Older servers: tabs + layouts, then the rows.)
  var want = '';
  try { want = localStorage.getItem('cb_tab:' + SHEET_ID) || ''; } catch (e) {}
  api('start', { tab: want })
    .catch(function(e) {
      if (!/unknown action/i.test(e.message)) throw e;
      return Promise.all([api('tabs'), api('layouts').catch(function() { return { layouts: {} }; })])
        .then(function(r) { return { title: r[0].title, tabs: r[0].tabs, layouts: r[1].layouts }; });
    })
    .then(function(res) {
      TABS = res.tabs || []; LAYOUTS = res.layouts || {};
      SETTINGS = Object.assign({ units: 'in', profile: {} }, LAYOUTS[SETTINGS_KEY] || {});
      SHEET_TITLE = res.title || '';
      if (res.title) { $('subTitle').textContent = res.title; document.title = res.title; }
      var sel = $('sheetSel');
      sel.innerHTML = TABS.map(function(t) { return '<option>' + esc(t.title) + '</option>'; }).join('');
      $('gridSheet').innerHTML = sel.innerHTML;
      var start = res.tab || want;
      if (!TABS.some(function(t) { return t.title === start; })) {
        start = (TABS.filter(function(t) { return LAYOUTS[t.title]; })[0] || TABS[0] || {}).title || '';
      }
      if (!start) { $('cardHolder').innerHTML = '<div class="muted">This spreadsheet has no tabs to design from.</div>'; return; }
      sel.value = start; $('gridSheet').value = start; refreshSheetCombos();
      selectTab(start, true, false, false, res.rows && res.tab === start ? res : null);
    })
    .catch(function(e) {
      $('cardHolder').innerHTML = '<div class="muted" style="max-width:360px;text-align:center">Couldn\'t open the sheet — ' + esc(e.message) +
        '<br><br>Make sure it\'s shared with <b>editor@zapsheets-480701.iam.gserviceaccount.com</b>.</div>';
    });
}

// ── Sheet picker: a combo box over the hidden <select>s (sheetSel, gridSheet) ────
// Type to filter the tabs; sheets that already have a layout show the layout icon.
var LAYOUT_ICON = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M5 8h14"/><rect x="8" y="11" width="8" height="4.5" rx="1" fill="currentColor" stroke="none"/><path d="M8 18.3h5"/></svg>';
function hasLayout(t) { return !!LAYOUTS[t] && t !== SETTINGS_KEY; }
function refreshSheetCombos() {
  ['sheetSel', 'gridSheet'].forEach(function(id) {
    var sel = $(id); if (!sel) return;
    var box = sel.nextElementSibling && sel.nextElementSibling.classList.contains('sheet-combo') ? sel.nextElementSibling : null;
    if (!box) { box = makeSheetCombo(sel); }
    var v = sel.value, inp = box.querySelector('input');
    if (document.activeElement !== inp) inp.value = v;
    box.querySelector('.sc-ico').innerHTML = hasLayout(v) ? LAYOUT_ICON : '';
    box.classList.toggle('has-layout', hasLayout(v));
  });
}
function makeSheetCombo(sel) {
  sel.style.display = 'none';
  var box = document.createElement('div');
  box.className = 'sheet-combo' + (sel.id === 'gridSheet' ? ' compact' : '');
  box.innerHTML = '<span class="sc-ico" title="Has a layout"></span><input class="inp sel-sheet" autocomplete="off" spellcheck="false" aria-label="Sheet" role="combobox" aria-expanded="false">' +
    '<button type="button" class="sc-caret" tabindex="-1" aria-label="Show sheets"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></button><div class="sc-list" role="listbox"></div>';
  sel.parentNode.insertBefore(box, sel.nextSibling);
  var inp = box.querySelector('input'), list = box.querySelector('.sc-list'), hi = 0, items = [];
  function draw(filter) {
    var f = (filter || '').trim().toLowerCase();
    items = TABS.map(function(t) { return t.title; }).filter(function(t) { return !f || t.toLowerCase().indexOf(f) >= 0; });
    hi = Math.max(0, items.indexOf(sel.value)); if (f) hi = 0;
    list.innerHTML = items.length ? items.map(function(t, i) {
      return '<div class="sc-item' + (i === hi ? ' hi' : '') + (t === sel.value ? ' cur' : '') + '" role="option" data-i="' + i + '">' +
        '<span class="sc-mark">' + (hasLayout(t) ? LAYOUT_ICON : '') + '</span><span class="sc-name">' + esc(t) + '</span>' + (t === sel.value ? '<span class="sc-check">✓</span>' : '') + '</div>';
    }).join('') : '<div class="sc-none">No sheet called “' + esc(filter) + '”</div>';
    var h = list.querySelector('.hi'); if (h) h.scrollIntoView({ block: 'nearest' });
  }
  function open() { if (box.classList.contains('open')) return; box.classList.add('open'); inp.setAttribute('aria-expanded', 'true'); draw(''); inp.select(); }
  function close(restore) { box.classList.remove('open'); inp.setAttribute('aria-expanded', 'false'); if (restore !== false) inp.value = sel.value; }
  function pick(t) {
    close(false); inp.value = t; inp.blur();
    if (t !== sel.value) { sel.value = t; selectTab(t); } else refreshSheetCombos();
  }
  inp.addEventListener('focus', open);
  inp.addEventListener('mousedown', function() { if (document.activeElement === inp) open(); });
  inp.addEventListener('input', function() { open(); draw(inp.value); });
  inp.addEventListener('keydown', function(k) {
    k.stopPropagation();
    if (k.key === 'ArrowDown' || k.key === 'ArrowUp') { k.preventDefault(); open(); hi = clamp(hi + (k.key === 'ArrowDown' ? 1 : -1), 0, items.length - 1);
      list.querySelectorAll('.sc-item').forEach(function(n, i) { n.classList.toggle('hi', i === hi); }); var h = list.querySelector('.hi'); if (h) h.scrollIntoView({ block: 'nearest' }); }
    else if (k.key === 'Enter') { k.preventDefault(); if (items[hi] != null) pick(items[hi]); }
    else if (k.key === 'Escape') { k.preventDefault(); close(); inp.blur(); }
  });
  inp.addEventListener('blur', function() { setTimeout(function() { if (document.activeElement !== inp) close(); }, 120); });
  box.querySelector('.sc-caret').addEventListener('mousedown', function(m) { m.preventDefault(); if (box.classList.contains('open')) { close(); inp.blur(); } else inp.focus(); });
  list.addEventListener('mousedown', function(m) { m.preventDefault(); var it = m.target.closest('.sc-item'); if (it) pick(items[+it.dataset.i]); });
  return box;
}

function selectTab(name, first, force, asked, pre) {   // pre: {rows, full} already fetched for this tab
  if (!first && dirty && !asked) {
    var from = curTab;
    $('sheetSel').value = from; $('gridSheet').value = from; refreshSheetCombos();
    askDialog({ title: 'Switch sheets?', message: 'You have unsaved changes to the “' + from + '” layout. They stay in this browser as a draft, but aren’t saved to your Sheet yet.',
                ok: 'Switch anyway', cancel: 'Stay here' })
      .then(function(yes) { if (yes) selectTab(name, false, force, true); });
    return;
  }
  if (_gridEdit) endGridEdit(true);
  $('sheetSel').value = name; $('gridSheet').value = name;
  curTab = name; refreshSheetCombos(); selId = null; multi = []; rowIdx = 0; undoStack = []; redoStack = []; GRID = null; _gridSel = null; _rowsStale = false;
  try { localStorage.setItem('cb_tab:' + SHEET_ID, name); } catch (e) {}
  layout = normalize(LAYOUTS[name] ? JSON.parse(JSON.stringify(LAYOUTS[name])) : newLayout());
  setDirty(false);
  _lastHidden = JSON.stringify(layout.hidden || []);
  // An unsaved draft from this browser wins (restored, still marked unsaved)
  try {
    var d = localStorage.getItem('cb_draft:' + SHEET_ID + ':' + name);
    if (d && d !== JSON.stringify(layout)) { layout = normalize(JSON.parse(d)); _lastHidden = JSON.stringify(layout.hidden || []); setDirty(true, true); toast('Restored your unsaved changes'); }
  } catch (e) {}
  $('dataList').innerHTML = '<div class="muted">Loading…</div>';
  $('cardHolder').innerHTML = '<div class="loading" style="width:240px;height:200px"><span class="spin"></span>Loading rows…</div>';
  if (!PUBLISHED[name]) loadPublished(name);
  var shown = false;
  function useRows(rows, partial) {
    ROWS = (rows || []).filter(function(r) { return Object.keys(r).some(function(k) { return !isInternal(k) && String(r[k]).trim(); }); });
    FIELDS = [];
    ROWS.forEach(function(r) { Object.keys(r).forEach(function(k) { if (!isInternal(k) && FIELDS.indexOf(k) < 0) FIELDS.push(k); }); });
    _rowsPartial = !!partial; showLoadStatus();
    rowIdx = Math.min(rowIdx, Math.max(0, ROWS.length - 1));
    renderData(); if (shown) render(); else fitZoom(); renderAttrs();
    shown = true;
    if (_activeTab === 'print') renderPrint();
    if (_activeTab === 'export') renderExport();
  }
  function allLoaded() {
    if (_activeTab === 'data') loadGrid(force);
    if (force) { toast('✓ Reloaded from the sheet'); autoRepublish(name); }
    else if (_republishWaiting === name) { _republishWaiting = ''; autoRepublish(name); }
  }
  function failed(e) {
    _rowsPartial = false; showLoadStatus();
    if (shown) { toast('Couldn\'t read the rest of "' + name + '" — ' + e.message, true); return; }
    ROWS = []; FIELDS = []; renderData(); render(); renderAttrs();
    toast('Couldn\'t read "' + name + '" — ' + e.message, true);
  }
  ROWS = []; _rowsPartial = true; showLoadStatus();   // until the first rows arrive
  // First the first few rows (quick), so the card shows; then every row
  var first = force ? Promise.resolve(null)
    : pre ? Promise.resolve(pre)
    : api('peek', { tab: name }).catch(function() { return null; });
  first.then(function(res) {
    if (curTab !== name) return;
    if (res && res.rows) { useRows(res.rows, !res.full); if (res.full) { allLoaded(); return; } }
    return api('rows', { tab: name, force: force ? '1' : '' }).then(function(r) {
      if (curTab !== name) return;
      useRows(r.rows, false); allLoaded();
    });
  }).catch(function(e) { if (curTab === name) failed(e); });
}
var _rowsPartial = false, _republishWaiting = '';
// Beside Save: "Loading rows…" until every row of the sheet is in
function showLoadStatus() {
  var el = $('loadStatus'); if (!el) return;
  el.hidden = !_rowsPartial;
  el.textContent = !_rowsPartial ? '' : ROWS.length ? 'Loading the other rows…' : 'Loading rows…';
}
function reloadSheet() { if (curTab) selectTab(curTab, true, true); }
function openInSheets() { window.open('https://docs.google.com/spreadsheets/d/' + SHEET_ID + '/edit', '_blank', 'noopener'); }

// ── Left panel: TEXT · DATA · IMAGE · SHAPE · GENERATED ──────────────────────
function chip(attrs, ico, name, sample, extraCls) {
  return '<div class="field-chip' + (extraCls ? ' ' + extraCls : '') + '" draggable="true" ' + attrs + ' ondragstart="chipDragStart(event)" onclick="chipClick(this, event)" title="' + (/data-field=/.test(attrs) ? 'Drag onto the card · click for options' : 'Drag onto the card') + '">' +
         '<span class="ico">' + ico + '</span><span class="nm">' + esc(name) + '</span>' + (sample ? '<span class="sample">' + esc(sample) + '</span>' : '') + '</div>';
}
// Each section can be folded away; the choice is remembered in this browser
function paletteClosed() { try { return JSON.parse(localStorage.getItem('cb_closed') || '{}'); } catch (e) { return {}; } }
function section(key, title, body, count, extra) {
  var closed = !!paletteClosed()[key];
  return '<div class="pal-sec' + (closed ? ' closed' : '') + '" data-sec="' + key + '">' +
         '<div class="pal-head" role="button" tabindex="0" onclick="toggleSection(\'' + key + '\')" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();toggleSection(\'' + key + '\')}"><span class="chev">▾</span>' + title +
         (extra || '') + (count != null ? '<span class="pal-count" id="' + key + 'Count">' + count + '</span>' : '') + '</div>' +
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
  h += section('data', 'Data', '<div id="dataList"><div class="muted">Loading…</div></div>', '',
    '<button type="button" class="eye-btn" id="eyeBtn" onclick="event.stopPropagation();toggleShowHidden()" style="display:none"></button>');
  h += section('image', 'Image', chip('data-add="image"', IMG_ICON, 'Image'));
  h += section('shape', 'Shape', '<div class="shape-grid">' + SHAPES.map(function(s) {
    var demo = { shape: s[0], w: 24, h: s[0] === 'line' ? 24 : 18, fill: s[0] === 'line' ? 'none' : '#ece9ff', stroke: '#4b3fa8', strokeW: 1.5, radius: s[0] === 'round' ? 5 : 0 };
    return '<div class="shape-chip" draggable="true" data-shape="' + s[0] + '" ondragstart="chipDragStart(event)" onclick="chipClick(this, event)" title="' + s[1] + ' — drag onto the card">' +
           '<span class="shape-ico">' + shapeSVG(demo) + '</span><span>' + s[1] + '</span></div>';
  }).join('') + '</div>');
  h += section('gen', 'Generated', GENS.map(function(g) { return chip('data-gen="' + g[0] + '"', g[1], g[2]); }).join(''));
  h += '<p class="muted" style="margin-top:.8rem">Drag onto the card to add. Click a Data field to hide it. Drop a picture file anywhere on the card to add it as an image.</p>';
  $('palette').innerHTML = h;
}
function renderData() {
  var el = $('dataList'); if (!el) return;
  var sample = ROWS[rowIdx] || ROWS[0] || {};
  var shown = visibleFields(), hid = FIELDS.filter(isHidden);
  if ($('dataCount')) $('dataCount').textContent = shown.length;
  var nf = '<div class="new-field" id="newField">' +
          '<button type="button" class="new-btn" onclick="showNewField()" title="Add a column to the sheet">New</button>' +
          '<form class="new-form" onsubmit="addField(event)"><input class="inp" id="newFieldName" maxlength="60" placeholder="New column name" title="Enter or click away to add · Esc to cancel" autocomplete="off" oninput="checkNewField()" onblur="addField()" onkeydown="if(event.key===\'Escape\'){this.value=\'\';hideNewField()}"></form>' +
          '<div class="new-msg" id="newFieldMsg"></div></div>';
  var h = '';   // New goes last: a new column is added after the others
  if (!FIELDS.length) h += '<div class="muted">No columns yet. The first row of the sheet holds the column names, with one card per row below — or add one with New.</div>';
  // The eye in the Data heading shows hidden fields too (greyed), in their place in the list
  var list = _hiddenOpen ? FIELDS : shown;
  h += list.map(function(f) {
    return isHidden(f) ? chip('data-field="' + esc(f) + '"', '⊘', f, '', 'is-hidden')
                       : chip('data-field="' + esc(f) + '"', DATA_ICON, f, looksLikeImage(f) ? '' : textVal(sample[f]), f === _newField ? 'just-added' : '');
  }).join('') + nf;
  el.innerHTML = h;
  var eye = $('eyeBtn');
  if (eye) {
    eye.style.display = hid.length ? '' : 'none';
    eye.classList.toggle('on', _hiddenOpen);
    eye.innerHTML = _hiddenOpen ? EYE_OPEN : EYE_SHUT;
    eye.title = (_hiddenOpen ? 'Hide' : 'Show') + ' the ' + hid.length + ' hidden field' + (hid.length === 1 ? '' : 's');
  }
}
// New column: added to the sheet's heading row, then ready to drag onto the card
var _newField = '';
function showNewField() { var b = $('newField'); b.classList.add('open'); $('newFieldName').value = ''; checkNewField(); $('newFieldName').focus(); }
function hideNewField() { var b = $('newField'); if (b) b.classList.remove('open'); if ($('newFieldMsg')) $('newFieldMsg').textContent = ''; }
function newFieldProblem(n) {
  if (!n) return ' ';
  if (isInternal(n) || /[{}]/.test(n)) return isInternal(n) ? '“' + n + '” is used by CardBoard' : 'Names can\'t use { or }';
  if (FIELDS.some(function(f) { return f.toLowerCase() === n.toLowerCase(); })) return 'There\'s already a column called "' + n + '"';
  return '';
}
function checkNewField() {
  var n = $('newFieldName').value.trim().replace(/\s+/g, ' ');
  $('newFieldMsg').textContent = newFieldProblem(n).trim();
}
// Leaving the box (or Enter) adds the column; an empty box just closes
var _addingField = false;
function addField(ev) {
  if (ev) ev.preventDefault();
  if (!hasAccess()) { requirePro('edit', function() {}); return; }
  var inp = $('newFieldName'); if (!inp || _addingField || !$('newField').classList.contains('open')) return;
  var n = inp.value.trim().replace(/\s+/g, ' ');
  if (!n) { hideNewField(); return; }
  var problem = newFieldProblem(n);
  if (problem) { $('newFieldMsg').textContent = problem; return; }   // stays open so it can be fixed
  if (!curTab) return;
  var tab = curTab; _addingField = true; inp.disabled = true; $('newFieldMsg').textContent = '';
  inp.parentNode.classList.add('busy');
  api('addfield', { tab: tab, name: n })
    .then(function(r) {
      _addingField = false;
      if (curTab !== tab) return;
      FIELDS.push(n); ROWS.forEach(function(row) { row[n] = ''; }); gridAddedColumn(n);
      _newField = n; renderData();
      var chipEl = document.querySelector('.field-chip[data-field="' + CSS.escape(n) + '"]'); if (chipEl) chipEl.scrollIntoView({ block: 'nearest' });
      toast('✓ Added column ' + (r.column ? r.column + ' ' : '') + '"' + n + '" to the sheet');
      setTimeout(function() { if (_newField === n) { _newField = ''; var c = document.querySelector('.field-chip.just-added'); if (c) c.classList.remove('just-added'); } }, 2500);
    })
    .catch(function(e) {
      _addingField = false; inp.disabled = false; inp.parentNode.classList.remove('busy');
      $('newFieldMsg').textContent = e.message;
    });
}
function chipSpec(c) {
  if (c.dataset.field) return 'data:' + c.dataset.field;
  if (c.dataset.shape) return 'shape:' + c.dataset.shape;
  if (c.dataset.gen)   return 'gen:' + c.dataset.gen;
  return 'add:' + c.dataset.add;
}
function chipDragStart(ev) { ev.dataTransfer.setData('text/plain', chipSpec(ev.currentTarget)); ev.dataTransfer.effectAllowed = 'copy'; }
// Adding is by drag and drop only; clicking a Data field opens its options
function chipClick(c, ev) {
  if (!layout) return;
  if (ev) ev.stopPropagation();
  if (c.dataset.field) { openFieldMenu(c); return; }
  c.classList.remove('nudge'); void c.offsetWidth; c.classList.add('nudge');   // a little wiggle: drag me
}
function closeFieldMenu() { var m = $('fieldMenu'); if (m) m.remove(); var a = document.querySelector('.field-chip.menu-open'); if (a) a.classList.remove('menu-open'); }
function openFieldMenu(chipEl) {
  var f = chipEl.dataset.field, open = chipEl.classList.contains('menu-open');
  closeFieldMenu(); if (open) return;
  var hidden = isHidden(f), used = layout.els.concat(layout.back.els).some(function(e) { return e.field === f || (e.kind === 'text' && (e.text || '').indexOf('{' + f + '}') >= 0); });
  var m = document.createElement('div'); m.id = 'fieldMenu'; m.className = 'field-menu';
  m.innerHTML = '<button type="button" onclick="setFieldHidden(' + esc(JSON.stringify(f)) + ',' + !hidden + ')">' + (hidden ? '👁 Show field' : '<span class="fm-ico">⊘</span> Hide field') + '</button>' +
    '<p>' + (hidden ? 'Brings it back in Design and Data.' : 'Hides it here and in the Data tab. The column stays in the sheet' + (used ? ', and it still shows on the card.' : '.')) + '</p>';
  chipEl.classList.add('menu-open');
  chipEl.parentNode.insertBefore(m, chipEl.nextSibling);
}
document.addEventListener('click', function(ev) { if (!ev.target.closest('#fieldMenu') && !ev.target.closest('.field-chip.menu-open')) closeFieldMenu(); });
// Hidden fields are kept with the layout (so they stay hidden on every device once saved)
function isHidden(f) { return !!(layout && layout.hidden && layout.hidden.indexOf(f) >= 0); }
function visibleFields() { return FIELDS.filter(function(f) { return !isHidden(f); }); }
function setFieldHidden(f, hide) {
  closeFieldMenu(); snapshot();
  layout.hidden = (layout.hidden || []).filter(function(x) { return x !== f; });
  if (hide) layout.hidden.push(f);
  changed(true); renderData();
  if (GRID) renderGrid();
}
function showAllFields() { if (!layout.hidden || !layout.hidden.length) return; snapshot(); layout.hidden = []; changed(true); renderData(); if (GRID) renderGrid(); }
var _hiddenOpen = false, _lastHidden = '[]';
try { _hiddenOpen = localStorage.getItem('cb_showHidden') === '1'; } catch (e) {}
var EYE_OPEN = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
var EYE_SHUT = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.1-5.9M9.9 5.2A9.6 9.6 0 0 1 12 5c7 0 11 7 11 7a19.9 19.9 0 0 1-2.2 3.2"/><path d="M14.1 14.1a3 3 0 0 1-4.2-4.2"/><path d="M1 1l22 22"/></svg>';
function toggleShowHidden() {
  _hiddenOpen = !_hiddenOpen;
  try { localStorage.setItem('cb_showHidden', _hiddenOpen ? '1' : '0'); } catch (e) {}
  renderData();
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
  snapshot(); curEls().push(e); selId = e.id; multi = []; normalizeZ(); changed();
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
function isPicEl(e) { return !!e && (e.kind === 'image' || (e.kind === 'data' && e.as === 'image')); }
// Image mapping: an image element can pick its picture from a field's value.
// e.map = {field, items:[{v, src}]}, used while e.mapOn. A value that matches an item uses its
// picture; otherwise a picture link (or an image [tag]) in the field; otherwise the element's own picture.
function picRef(s) {   // a picture address, or the picture of an image tag written [name]
  var m = /^\s*\[([^\[\]]+)\]\s*$/.exec(s || '');
  if (m) { var t = tagsOn() && TAGMAP[tagKey(m[1])]; return t ? tagPicture(t.value) : ''; }
  return s || '';
}
function imgSrcFor(e, row) {
  var mp = e.mapOn && e.map;
  if (!mp || !mp.field || !row) return picRef(e.src);
  var raw = cleanVal(row[mp.field]).trim(), low = raw.toLowerCase();
  var hit = (mp.items || []).filter(function(it) { return it.src && String(it.v || '').trim() && String(it.v).trim().toLowerCase() === low; })[0];
  if (hit) return picRef(hit.src);
  if (/^\[[^\[\]]+\]$/.test(raw)) { var tp = picRef(raw); if (tp) return tp; }
  if (/^(https?:\/\/|=IMAGE\(|\[[^\]]*\]\(https?:)/i.test(raw)) return raw;
  return picRef(e.src);
}
function curSrc(e) { return e.kind === 'data' ? cleanVal((ROWS[rowIdx] || {})[e.field]) : (e.src || ''); }
function setImageSrc(id, src) {
  var e = elById(id); if (!e) return;
  if (e.kind === 'data') { setRowPicture(e, src); return; }   // a Data column: the picture goes into this row's cell
  snapshot(); e.src = src; changed(selId !== id);
  if (selId === id) renderAttrs();
}
// Data column shown as an image: write the picture's address into the current row's cell
function gridMatch(row, gr) {   // does this sheet row (as typed) look like this card's row?
  var n = 0;
  for (var c = 0; c < GRID.header.length; c++) {
    var h = GRID.header[c]; if (!h || !(h in row)) continue;
    var gv = String(gr.values[c] == null ? '' : gr.values[c]).trim(), rv = cleanVal(row[h]).trim();
    if (/^=/.test(gv) || /\]\(/.test(rv)) continue;   // formulas and links read differently; skip them
    if (gv !== rv) return false;
    n++;
  }
  return n > 0;
}
function sheetRowFor(row, like) {   // like: the row's values as the sheet has them (when the card already shows an edit)
  like = like || row;
  var start = Promise.resolve();
  if (!GRID || GRID.tab !== curTab) {
    var tab = curTab;
    start = api('grid', { tab: tab }).then(function(r) { if (curTab === tab) GRID = { tab: tab, header: r.header || [], rows: r.rows || [] }; });
  }
  return start.then(function() {
    var i = ROWS.indexOf(row), guess = GRID.rows.filter(function(g) { return !g.draft; })[i];
    if (guess && gridMatch(like, guess)) return guess;
    var all = GRID.rows.filter(function(g) { return !g.draft && gridMatch(like, g); });
    if (all.length === 1) return all[0];
    throw new Error(all.length ? 'several rows look the same — set the picture in the Data tab' : 'couldn\'t find this row in the sheet — try Fetch');
  });
}
function setRowPicture(e, url) {
  var row = ROWS[rowIdx]; if (!row) { toast('There\'s no row to put the picture in', true); return; }
  var tab = curTab, field = e.field;
  UPLOADING[e.id] = true; render(); if (selId === e.id) renderAttrs();
  sheetRowFor(row).then(function(gr) {
    var c = GRID.header.indexOf(field); if (c < 0) throw new Error('the ' + field + ' column wasn\'t found');
    return api('setcell', { tab: tab, row: gr.row, field: field, value: url, old: gr.values[c] || '' }).then(function(res) {
      gr.values[c] = res && res.value != null ? res.value : url;
      row[field] = url;
      return gr.row;
    });
  }).then(function(sheetRow) {
    delete UPLOADING[e.id]; render(); renderData(); if (_activeTab === 'data') renderGrid(); if (selId === e.id) renderAttrs();
    toast(url ? '✓ Picture saved in ' + field + ', row ' + sheetRow : 'Picture removed from ' + field + ', row ' + sheetRow);
    autoRepublish(tab);
  }).catch(function(err) {
    delete UPLOADING[e.id]; render(); if (selId === e.id) renderAttrs();
    toast(err.message === 'conflict' ? 'That cell was changed in the sheet — Fetch to see it, then try again' : 'Couldn\'t save the picture — ' + err.message, true);
  });
}
function uploadImage(id, file, slot) {   // slot: a mapping item's index, for its picture
  if (!isImageFile(file)) { toast('Use a PNG, JPG, GIF or WebP picture', true); return; }
  if (!requirePro('upload', function() { uploadImage(id, file, slot); })) return;
  if (file.size > 10 * 1024 * 1024) { toast('That picture is over 10 MB', true); return; }
  UPLOADING[id] = true; render(); if (selId === id) renderAttrs();
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('app', 'cardboard'); fd.append('file', file);
  cbPost(APP_BASE + 'push/uploadImage.php', fd)
    .then(function(r) {
      if (!r || r.error || !r.url) throw new Error(r && r.error ? r.error : 'Upload failed');
      delete UPLOADING[id]; if (slot == null) setImageSrc(id, r.url); else setMapSrc(id, slot, r.url);
    })
    .catch(function(err) { delete UPLOADING[id]; render(); toast('Couldn\'t upload — ' + err.message, true); });
}
function closeImagePicker() { var p = $('imgPicker'); if (p) p.remove(); }
function openImagePicker(ev, id, slot) {   // slot: pick the picture for one value of an image mapping
  if (ev) { ev.preventDefault(); ev.stopPropagation(); }
  var e = elById(id); if (!e) return;
  if (selId !== id) selectEl(id);
  closeImagePicker();
  var cur = slot == null ? curSrc(e) : ((e.map && e.map.items[slot]) || {}).src || '';
  var set = function(u) { if (slot == null) setImageSrc(id, u); else setMapSrc(id, slot, u); };
  var p = document.createElement('div');
  p.id = 'imgPicker'; p.className = 'img-picker';
  p.innerHTML =
    '<div class="ip-drop" id="ipDrop">' + IMG_ICON + '<b>Drop a picture here</b><span>or</span>' +
      '<button type="button" class="ip-choose" onclick="$(\'ipFile\').click()">Choose a file…</button>' +
      '<input type="file" id="ipFile" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none"></div>' +
    '<span class="mini">Or a web address</span>' +
    '<div class="ip-url"><input class="inp" id="ipUrl" placeholder="Link, or an image tag like [fire]" value="' + esc(/\/images\/[A-Za-z0-9_\-]+\/[0-9a-f]{20}\.\w+$/.test(cur) ? '' : cur) + '">' +
      '<button type="button" class="attr-btn" id="ipUse">Use</button></div>' +
    '<p class="muted">Links are shown straight from Dropbox, Drive or the website, so when you change the picture there it changes here too. In Drive, share it as “Anyone with the link”.</p>' +
    (slot != null ? '<p class="muted ip-row">Used when <b>' + esc(e.map.field) + '</b> is <b>' + esc(((e.map.items[slot] || {}).v) || '…') + '</b>.</p>' : '') +
    (e.kind === 'data' ? '<p class="muted ip-row">For <b>row ' + (rowIdx + 1) + '</b> — saved in its <b>' + esc(e.field) + '</b> cell in the sheet. Uploads are kept on zapsheets.com.</p>' : '') +
    (cur ? '<button type="button" class="attr-btn danger ip-remove" id="ipRemove">Remove picture</button>' : '');
  document.body.appendChild(p);
  // Place it beside the picture button (or the element), kept on screen
  var anchor = slot != null ? document.querySelector('.map-slot[data-i="' + slot + '"]') : $('cardHolder').querySelector('.el[data-id="' + id + '"]'), r = anchor ? anchor.getBoundingClientRect() : { right: innerWidth / 2, top: 100 };
  var left = Math.min(r.right + 10, innerWidth - p.offsetWidth - 12), top = clamp(r.top, 70, innerHeight - p.offsetHeight - 12);
  if (left < r.right && anchor) left = Math.max(12, r.left - p.offsetWidth - 10);
  p.style.left = left + 'px'; p.style.top = top + 'px';
  var drop = $('ipDrop');
  drop.addEventListener('dragover', function(d) { d.preventDefault(); drop.classList.add('over'); });
  drop.addEventListener('dragleave', function() { drop.classList.remove('over'); });
  drop.addEventListener('drop', function(d) {
    d.preventDefault(); d.stopPropagation(); drop.classList.remove('over');
    var f = droppedFile(d.dataTransfer), l = droppedLink(d.dataTransfer);
    if (f) { closeImagePicker(); uploadImage(id, f, slot); } else if (l) { closeImagePicker(); set(l); }
  });
  $('ipFile').addEventListener('change', function() { var f = this.files[0]; if (f) { closeImagePicker(); uploadImage(id, f, slot); } });
  function useUrl() {
    var u = $('ipUrl').value.trim();
    if (/^\{?\[[^\[\]]+\]\}?$/.test(u)) u = braced(u);   // an image tag
    else if (u && !imageUrl(u)) { toast('That doesn\'t look like a web address or a [tag]', true); return; }
    closeImagePicker(); set(u);
  }
  $('ipUse').onclick = useUrl;
  $('ipUrl').addEventListener('keydown', function(k) { if (k.key === 'Enter') useUrl(); if (k.key === 'Escape') closeImagePicker(); });
  if ($('ipRemove')) $('ipRemove').onclick = function() { closeImagePicker(); set(''); };
}
document.addEventListener('pointerdown', function(ev) {
  var p = $('imgPicker'); if (p && !p.contains(ev.target) && !ev.target.closest('.img-btn')) closeImagePicker();
});
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeImagePicker(); });
// A paste with a picture link or file while an image is selected sets it
document.addEventListener('paste', function(ev) {
  var e = elById(selId); if (_activeTab !== 'design' || !e || !isPicEl(e) || /^(INPUT|TEXTAREA)$/.test((document.activeElement || {}).tagName || '')) return;
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
    '<div class="side-label">' + (side === 'back' ? 'Back' : 'Front') + '</div>' +
    '<button type="button" class="flip-btn' + (side === 'back' ? ' on' : '') + '" onclick="flipCard()" title="Flip the card — show the ' + (side === 'back' ? 'front' : 'back') + '" aria-label="Flip the card">' + FLIP_ICON + '</button>';
  holder.insertAdjacentHTML('beforeend', alignDotsHTML());
  var card = holder.firstChild;
  card.style.transform = 'scale(' + zoom + ')';
  card.style.setProperty('--z', zoom * PX);
  $('rowInfo').textContent = ROWS.length ? 'Row ' + (rowIdx + 1) + ' of ' + ROWS.length + (_rowsPartial ? '…' : '') : (_rowsPartial ? 'Loading rows…' : 'No rows');
  $('rowInfo').title = _rowsPartial ? 'Loading the other rows…' : '';
  $('rowInfo').classList.toggle('partial', _rowsPartial);
  $('rowPrev').disabled = rowIdx <= 0; $('rowNext').disabled = rowIdx >= ROWS.length - 1;
  $('zoomInfo').textContent = Math.round(zoom * 100) + '%';
  $('undoBtn').disabled = !undoStack.length; $('redoBtn').disabled = !redoStack.length;
}
// ── Align ────────────────────────────────────────────────────────────────────
// Dots past the card's right and bottom edges. With one element or one group selected they
// line it up with the card's edges or middle; with several, they line those up with each other.
// A whole group in the selection moves as one unit.
function alignPlan() {
  var sel = selEls(); if (!sel.length) return null;
  var seen = {}, units = [];
  if (!selGroupId()) sel.forEach(function(e) {
    if (e.group && groupMembers(e.group).every(function(m) { return isSel(m.id); })) {
      if (seen[e.group]) return; seen[e.group] = true; units.push(groupMembers(e.group));
    } else units.push([e]);
  });
  if (units.length > 1) return { card: false, units: units, ref: selBox(sel) };
  return { card: true, units: [sel], ref: { x: 0, y: 0, w: layout.card.w, h: layout.card.h } };
}
var ALIGN_TITLES = {
  sel:  { top: 'Line up the tops', middle: 'Line up the middles', bottom: 'Line up the bottoms', left: 'Line up the left edges', center: 'Line up the centers', right: 'Line up the right edges' },
  card: { top: 'Move to the top of the card', middle: 'Center up and down on the card', bottom: 'Move to the bottom of the card', left: 'Move to the left edge of the card', center: 'Center across the card', right: 'Move to the right edge of the card' }
};
function alignDotsHTML() {
  var plan = alignPlan(); if (!plan) return '';
  var bb = plan.ref, T = ALIGN_TITLES[plan.card ? 'card' : 'sel'], k = PX * zoom, W = layout.card.w * k, H = layout.card.h * k, h = '';
  var ys = { top: bb.y, middle: bb.y + bb.h / 2, bottom: bb.y + bb.h }, xs = { left: bb.x, center: bb.x + bb.w / 2, right: bb.x + bb.w };
  var offR = 34, offB = 34;   // dots sit out past the card's right and bottom edges
  Object.keys(ys).forEach(function(n) {
    var y = Math.round(ys[n] * k);
    h += '<div class="al-guide al-h" style="left:' + W + 'px;top:' + y + 'px;width:' + offR + 'px"></div>' +
      '<button type="button" class="al-dot" style="left:' + (W + offR) + 'px;top:' + y + 'px" onclick="alignSel(\'' + n + '\')" title="' + T[n] + '" aria-label="' + T[n] + '"></button>' +
      '<div class="al-guide al-h in" style="left:0;top:' + y + 'px;width:' + W + 'px"></div>';
  });
  Object.keys(xs).forEach(function(n) {
    var x = Math.round(xs[n] * k);
    h += '<div class="al-guide al-v" style="top:' + H + 'px;left:' + x + 'px;height:' + offB + 'px"></div>' +
      '<button type="button" class="al-dot" style="top:' + (H + offB) + 'px;left:' + x + 'px" onclick="alignSel(\'' + n + '\')" title="' + T[n] + '" aria-label="' + T[n] + '"></button>' +
      '<div class="al-guide al-v in" style="top:0;left:' + x + 'px;height:' + H + 'px"></div>';
  });
  return h;
}
function alignSel(edge) {
  var plan = alignPlan(); if (!plan) return;
  var bb = plan.ref, moved = false;
  plan.units.forEach(function(u) {
    var b = selBox(u), dx = 0, dy = 0;
    if (edge === 'left') dx = bb.x - b.x;
    else if (edge === 'center') dx = bb.x + bb.w / 2 - (b.x + b.w / 2);
    else if (edge === 'right') dx = bb.x + bb.w - (b.x + b.w);
    else if (edge === 'top') dy = bb.y - b.y;
    else if (edge === 'middle') dy = bb.y + bb.h / 2 - (b.y + b.h / 2);
    else if (edge === 'bottom') dy = bb.y + bb.h - (b.y + b.h);
    if (Math.abs(dx) < .05 && Math.abs(dy) < .05) return;
    if (!moved) { snapshot(); moved = true; }
    u.forEach(function(e) { e.x = r1(e.x + dx); e.y = r1(e.y + dy); });
  });
  if (!moved) { toast(plan.card ? 'Already there' : 'Already lined up'); return; }
  changed(true); var one = elById(selId); if (one) syncGeomInputs(one); else renderAttrs();
}
// Turn the card over, with a flip animation
var _flipping = false;
function flipCard() {
  if (!layout || _flipping) return;
  closeImagePicker();
  var holder = $('cardHolder'), reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  function swap() { side = side === 'back' ? 'front' : 'back'; selId = null; multi = []; render(); renderAttrs(); }
  if (reduce || !holder.animate) { swap(); return; }
  _flipping = true;
  var out = holder.animate([{ transform: 'perspective(1400px) rotateY(0deg)' }, { transform: 'perspective(1400px) rotateY(90deg)' }], { duration: 170, easing: 'ease-in' });
  out.onfinish = function() {
    swap();
    var back = holder.animate([{ transform: 'perspective(1400px) rotateY(-90deg)' }, { transform: 'perspective(1400px) rotateY(0deg)' }], { duration: 210, easing: 'ease-out' });
    back.onfinish = function() { _flipping = false; };
  };
}
function stepRow(d) { rowIdx = clamp(rowIdx + d, 0, Math.max(0, ROWS.length - 1)); render(); renderData(); if (isPicEl(elById(selId))) renderAttrs(); }
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
      var hit = document.elementFromPoint(ev.clientX, ev.clientY), node = hit && hit.closest('.el.k-image, .el.as-image'), target = node && elById(node.dataset.id);
      var place = function() {
        var e = target;
        if (!e) { addAt('add:image', clamp(p.x, 0, c.w), clamp(p.y, 0, c.h)); e = elById(selId); }
        if (file) uploadImage(e.id, file); else setImageSrc(e.id, link);
      };
      if (!requirePro('upload', place)) return;   // locked: don't leave an empty image behind
      place();
      return;
    }
    if (d.indexOf(':') < 0 || /^https?:/i.test(d)) return;
    addAt(d, clamp(p.x, 0, c.w), clamp(p.y, 0, c.h));
  });
})();

// Double-click a picture on the card to change it (the panel on the right has the same button)
$('cardHolder').addEventListener('dblclick', function(ev) {
  var node = ev.target.closest('.el'), e = node && elById(node.dataset.id);
  if (e && isPicEl(e)) openImagePicker(ev, e.id);
  else if (e && e.kind === 'data') editCellInPlace(e);
});
// Double-click a Data field shown as text to edit this row's value right on the card.
// Enter saves to the sheet (Shift+Enter for a new line), Esc cancels, clicking away saves.
function editCellInPlace(e) {
  if (!requirePro('edit', function() {})) return;
  var row = ROWS[rowIdx]; if (!row) { toast('There\'s no row to edit', true); return; }
  if (!isSel(e.id) || multi.length) setSel([e.id]);
  var node = $('cardHolder').querySelector('.el[data-id="' + e.id + '"]'), inner = node && node.querySelector('.el-inner');
  if (!inner) return;
  var old = cleanVal(row[e.field]), done = false;
  node.classList.add('editing');
  inner.textContent = old;
  try { inner.contentEditable = 'plaintext-only'; } catch (x) {}
  if (inner.contentEditable !== 'plaintext-only') inner.contentEditable = 'true';
  inner.focus();
  var rg = document.createRange(); rg.selectNodeContents(inner); var sl = getSelection(); sl.removeAllRanges(); sl.addRange(rg);
  function finish(save) {
    if (done) return; done = true;
    var v = inner.innerText.replace(/\r/g, '').replace(/\n$/, '');
    inner.removeAttribute('contenteditable'); node.classList.remove('editing');
    if (save && v !== old) setRowCell(e, v); else render();
  }
  inner.addEventListener('keydown', function(k) {
    k.stopPropagation();
    if (k.key === 'Enter' && !k.shiftKey) { k.preventDefault(); finish(true); }
    else if (k.key === 'Escape') { k.preventDefault(); finish(false); }
  });
  inner.addEventListener('blur', function() { finish(true); });
}
// Write a text value into this row's cell for a Data field. Shown on the card straight away;
// put back if the sheet can't be updated.
function setRowCell(e, value) {
  var row = ROWS[rowIdx]; if (!row) return;
  var tab = curTab, field = e.field, before = row[field];
  var like = Object.assign({}, row); like[field] = before;
  row[field] = value; render(); renderData();
  sheetRowFor(row, like).then(function(gr) {
    var c = GRID.header.indexOf(field); if (c < 0) throw new Error('the ' + field + ' column wasn\'t found');
    if (/^=/.test(String(gr.values[c] == null ? '' : gr.values[c]))) throw new Error('that cell is a formula — change it in the sheet');
    return api('setcell', { tab: tab, row: gr.row, field: field, value: value, old: gr.values[c] == null ? '' : gr.values[c] }).then(function(res) {
      gr.values[c] = res && res.value != null ? res.value : value;
      return gr.row;
    });
  }).then(function(sheetRow) {
    render(); renderData(); if (_activeTab === 'data') renderGrid(); if (selId === e.id) renderAttrs();
    toast('✓ Saved in ' + field + ', row ' + sheetRow);
    autoRepublish(tab);
  }).catch(function(err) {
    if (row[field] === value) row[field] = before;
    render(); renderData();
    toast(err.message === 'conflict' ? 'That cell was changed in the sheet — Fetch to see it, then try again' : 'Couldn\'t save — ' + err.message, true);
  });
}
// Move / resize elements on the card
$('cardHolder').addEventListener('pointerdown', function(ev) {
  if (ev.target.closest('.img-btn') || ev.target.closest('.el.editing')) return;
  var elDom = ev.target.closest('.el'); if (!elDom || ev.button > 0) return;
  var e = elById(elDom.dataset.id); if (!e) return;
  ev.preventDefault();
  var handle = ev.target.dataset.h || '';
  if (handle === 'rot' && isSel(e.id) && !multi.length) { rotateDrag(ev, e, elDom); return; }   // before Shift-click, so Shift can snap the angle
  // Clicking part of a group picks the whole group; Shift/⌘-click adds to (or takes from) the selection
  var unit = e.group ? groupMembers(e.group).map(function(x) { return x.id; }) : [e.id];
  if (ev.shiftKey || ev.metaKey || ev.ctrlKey) {
    var cur = selIdsNow(), has = unit.every(function(id) { return cur.indexOf(id) >= 0; });
    setSel(has ? cur.filter(function(id) { return unit.indexOf(id) < 0; }) : cur.concat(unit));
    return;
  }
  if (!unit.every(isSel)) setSel(unit);
  var start = ptFromEvent(ev), o = { x: e.x, y: e.y, w: e.w, h: e.h }, c = layout.card, moved = false;
  var group = multi.length ? curEls().filter(function(x) { return multi.indexOf(x.id) >= 0; }) : null;
  var origin = group && group.map(function(x) { return { x: x.x, y: x.y }; });
  if (group) handle = '';
  function mv(m) {
    var p = ptFromEvent(m), dx = p.x - start.x, dy = p.y - start.y;
    if (!moved && Math.abs(dx) + Math.abs(dy) < .8) return;
    if (!moved) { snapshot(); moved = true; $('cardHolder').classList.add('moving'); }   // hide the align dots until it's dropped
    var snap = m.altKey ? .1 : 1;
    function sn(v) { return Math.round(v / snap) * snap; }
    if (group) {   // move everything selected together
      var ddx = sn(dx), ddy = sn(dy);
      group.forEach(function(x, i) {
        x.x = r1(origin[i].x + ddx); x.y = r1(origin[i].y + ddy);
        var nd = $('cardHolder').querySelector('.el[data-id="' + x.id + '"]'); if (nd) { nd.style.left = x.x + 'pt'; nd.style.top = x.y + 'pt'; }
      });
      var bx = $('cardHolder').querySelector('.sel-box'), b2 = selBox(group);
      if (bx && b2) { bx.style.left = b2.x + 'pt'; bx.style.top = b2.y + 'pt'; }
      return;
    }
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
    $('cardHolder').classList.remove('moving');
    if (moved) changed(!group);   // a group's panel shows its position, so refresh it
  }
  document.addEventListener('pointermove', mv); document.addEventListener('pointerup', up);
});

// Drag the round handle above an element to turn it about its middle. Shift snaps to 15°.
function rotateDrag(ev, e, node) {
  var r = node.getBoundingClientRect(), cx = r.left + r.width / 2, cy = r.top + r.height / 2;
  function ang(m) { return Math.atan2(m.clientY - cy, m.clientX - cx) * 180 / Math.PI; }
  var a0 = ang(ev), rot0 = +e.rot || 0, moved = false;
  function mv(m) {
    var d = ang(m) - a0; if (!moved && Math.abs(d) < 1) return;
    if (!moved) { snapshot(); moved = true; $('cardHolder').classList.add('moving'); }
    var v = rot0 + d; v = m.shiftKey ? Math.round(v / 15) * 15 : Math.round(v);
    v = ((v % 360) + 540) % 360 - 180; if (v === -180) v = 180;
    e.rot = v;
    node.style.transform = v ? 'rotate(' + v + 'deg)' : '';
    var inp = $('a_rot'); if (inp) inp.value = v;
    node.setAttribute('data-angle', v + '°');
  }
  function up() {
    document.removeEventListener('pointermove', mv); document.removeEventListener('pointerup', up);
    $('cardHolder').classList.remove('moving'); node.removeAttribute('data-angle');
    if (moved) changed();
  }
  document.addEventListener('pointermove', mv); document.addEventListener('pointerup', up);
}

// ── Keyboard ──────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function(ev) {
  var typing = /^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '') || !!(document.activeElement && document.activeElement.isContentEditable);
  var mod = ev.metaKey || ev.ctrlKey;
  if (mod && ev.key.toLowerCase() === 's') { ev.preventDefault(); saveLayout(); return; }
  if (typing || _activeTab !== 'design' || !layout || !hasAccess()) return;   // read-only once the free week is over
  if (mod && ev.key.toLowerCase() === 'z') { ev.preventDefault(); if (ev.shiftKey) redo(); else undo(); return; }
  if (mod && ev.key.toLowerCase() === 'd') { ev.preventDefault(); duplicateSel(); return; }
  if (mod && ev.key.toLowerCase() === 'g') { ev.preventDefault(); if (ev.shiftKey) ungroupSel(); else groupSel(); return; }
  if (mod && ev.key.toLowerCase() === 'a') { ev.preventDefault(); setSel(curEls().map(function(x) { return x.id; })); return; }
  var sel = selEls(); if (!sel.length) return;
  if (ev.key === 'Delete' || ev.key === 'Backspace') { ev.preventDefault(); deleteSel(); return; }
  if (ev.key === 'Escape') { selectEl(null); return; }
  var step = ev.shiftKey ? 10 : 1, d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[ev.key];
  if (d) { ev.preventDefault(); snapshot(); sel.forEach(function(x) { x.x = r1(x.x + d[0]); x.y = r1(x.y + d[1]); }); changed(true); if (sel.length === 1) syncGeomInputs(sel[0]); }
});

// ── Selection, undo, saving ───────────────────────────────────────────────────
function elById(id) { return layout && curEls().filter(function(e) { return e.id === id; })[0]; }
function selectEl(id) { setSel(id ? [id] : []); }
function snapshot() { undoStack.push(JSON.stringify(layout)); if (undoStack.length > 80) undoStack.shift(); redoStack = []; }
function undo() { if (!undoStack.length) return; redoStack.push(JSON.stringify(layout)); layout = JSON.parse(undoStack.pop()); fixSel(); changed(); }
function redo() { if (!redoStack.length) return; undoStack.push(JSON.stringify(layout)); layout = JSON.parse(redoStack.pop()); fixSel(); changed(); }
function changed(keepAttrs) {
  setDirty(true);
  if (layout && JSON.stringify(layout.hidden || []) !== _lastHidden) { _lastHidden = JSON.stringify(layout.hidden || []); renderData(); if (GRID && _activeTab === 'data') renderGrid(); }
  try { localStorage.setItem('cb_draft:' + SHEET_ID + ':' + curTab, JSON.stringify(layout)); } catch (e) {}
  render();
  if (!keepAttrs) renderAttrs(); else renderLayers();
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
  if (!requirePro('save', saveLayout)) return;
  var btn = $('saveBtn'), snap = JSON.stringify(layout), tab = curTab;
  btn.disabled = true; btn.textContent = 'Saving…';
  api('save', { tab: tab, layout: snap })
    .then(function() {
      LAYOUTS[tab] = JSON.parse(snap); refreshSheetCombos();
      try { localStorage.removeItem('cb_draft:' + SHEET_ID + ':' + tab); } catch (e) {}
      if (curTab === tab && JSON.stringify(layout) === snap) setDirty(false);
      toast('✓ Layout saved to your Sheet');
      autoRepublish(tab);
    })
    .catch(function(e) { toast('Couldn\'t save — ' + e.message, true); setDirty(true); })
    .then(function() { btn.textContent = 'Save'; btn.disabled = !dirty; });
}
window.addEventListener('beforeunload', function(ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });

function deleteSel() {
  var ids = selIdsNow(); if (!ids.length) return; snapshot();
  var face = faceOf(layout, side);
  face.els = face.els.filter(function(x) { return ids.indexOf(x.id) < 0; });
  cleanGroups(); selId = null; multi = []; changed();
}
function duplicateSel() {
  var sel = selEls(); if (!sel.length) return; snapshot();
  var gmap = {}, top = curEls().reduce(function(m, x) { return Math.max(m, x.z || 1); }, 0), ids = [];
  sel.sort(function(a, b) { return (a.z || 1) - (b.z || 1); }).forEach(function(e) {
    var c = JSON.parse(JSON.stringify(e)); c.id = uid(); c.x = r1(c.x + 8); c.y = r1(c.y + 8); c.z = ++top;
    if (c.kind === 'gen') c.seed = uid();   // a copy draws its own random values
    if (c.group) {   // copies of a group become a new group
      if (!gmap[c.group]) { var g = groupById(c.group); gmap[c.group] = newGroup((g ? g.name : 'Group') + ' copy'); }
      c.group = gmap[c.group];
    }
    curEls().push(c); ids.push(c.id);
  });
  normalizeZ(); setSelQuiet(ids); changed();
}
function reorderSel(dir) {
  var sel = selEls(); if (!sel.length) return; snapshot();
  var zs = curEls().map(function(x) { return x.z || 1; }), base = dir > 0 ? Math.max.apply(null, zs) + 1 : Math.min.apply(null, zs) - sel.length;
  sel.sort(function(a, b) { return (a.z || 1) - (b.z || 1); }).forEach(function(x, i) { x.z = base + i; });
  normalizeZ(); changed(true);
}

// ── Selection of several elements, groups and the Layers list ────────────────
// A group is a name kept on the side of the card it belongs to (face.groups) plus
// the same "group" id on each member. Members always sit next to each other in
// the stacking order, so the group moves up and down as one.
function face() { return faceOf(layout, side); }
function isSel(id) { return id === selId || multi.indexOf(id) >= 0; }
function selIdsNow() { return multi.length ? multi.slice() : (selId ? [selId] : []); }
function selEls() { var ids = selIdsNow(); return curEls().filter(function(x) { return ids.indexOf(x.id) >= 0; }); }
function setSelQuiet(ids) {
  var all = curEls().map(function(x) { return x.id; });
  ids = ids.filter(function(id, i) { return all.indexOf(id) >= 0 && ids.indexOf(id) === i; });
  if (ids.length > 1) { multi = ids; selId = null; } else { multi = []; selId = ids[0] || null; }
}
function setSel(ids) { closeImagePicker(); setSelQuiet(ids); render(); renderAttrs(); }
function fixSel() { setSelQuiet(selIdsNow()); }
function selBox(els) {
  if (!els.length) return null;
  var x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
  els.forEach(function(e) { x1 = Math.min(x1, e.x); y1 = Math.min(y1, e.y); x2 = Math.max(x2, e.x + e.w); y2 = Math.max(y2, e.y + e.h); });
  return { x: r1(x1), y: r1(y1), w: r1(x2 - x1), h: r1(y2 - y1) };
}
function groupById(gid) { return (face().groups || []).filter(function(g) { return g.id === gid; })[0]; }
function groupMembers(gid) { return curEls().filter(function(x) { return x.group === gid; }); }
function newGroup(name) { var f = face(); f.groups = f.groups || []; var g = { id: 'g' + uid().slice(1), name: name }; f.groups.push(g); return g.id; }
// The selected group's id, when the selection is exactly one whole group
function selGroupId() {
  var sel = selEls(); if (sel.length < 2) return '';
  var gid = sel[0].group; if (!gid || !sel.every(function(x) { return x.group === gid; })) return '';
  return groupMembers(gid).length === sel.length ? gid : '';
}
function cleanGroups() {   // drop groups left with fewer than two members
  [layout, layout.back].forEach(function(f) {
    f.groups = (f.groups || []).filter(function(g) {
      var n = f.els.filter(function(x) { return x.group === g.id; });
      if (n.length < 2) { n.forEach(function(x) { delete x.group; }); return false; }
      return true;
    });
  });
}
// Stacking order as a list of entries, back to front: an element, or a group with its members
function entries() {
  var els = curEls().slice().sort(function(a, b) { return (a.z || 1) - (b.z || 1); }), out = [];
  els.forEach(function(e, i) {
    if (!e.group) { out.push({ el: e }); return; }
    var members = els.filter(function(x) { return x.group === e.group; });
    if (members[members.length - 1] === e) out.push({ group: e.group, els: members });   // the group sits where its top member is
  });
  return out;
}
function applyEntries(list) {   // list back to front → z 1, 2, 3…
  var z = 1;
  list.forEach(function(en) { (en.group ? en.els : [en.el]).forEach(function(x) { x.z = z++; }); });
}
function normalizeZ() { cleanGroups(); applyEntries(entries()); }
function groupSel() {
  var sel = selEls(); if (sel.length < 2) return;
  snapshot();
  var gid = newGroup('Group ' + ((face().groups || []).length + 1));
  sel.forEach(function(x) { x.group = gid; });
  normalizeZ(); setSelQuiet(sel.map(function(x) { return x.id; })); changed();
  toast('Grouped ' + sel.length + ' elements');
}
function ungroupSel() {
  var sel = selEls(), gids = []; sel.forEach(function(x) { if (x.group && gids.indexOf(x.group) < 0) gids.push(x.group); });
  if (!gids.length) return; snapshot();
  sel.forEach(function(x) { delete x.group; }); normalizeZ(); changed();
}
function renameGroup(gid, name) { var g = groupById(gid); if (!g || !name.trim() || g.name === name.trim()) return; snapshot(); g.name = name.trim(); changed(true); }

// The Layers list (toggled by the button at the top of the right-hand panel); front-most at the top
var _layersOpen = false, _layerFold = {}, _lyClick = {};
try { _layersOpen = localStorage.getItem('cb_layers') === '1'; } catch (e) {}
function toggleLayers() {
  _layersOpen = !_layersOpen;
  try { localStorage.setItem('cb_layers', _layersOpen ? '1' : '0'); } catch (e) {}
  renderLayers();
}
function elLabel(e) {
  if (e.kind === 'data') return e.field;
  if (e.kind === 'text') return (e.text || 'Text').replace(/\s+/g, ' ').slice(0, 40);
  if (e.kind === 'shape') return (SHAPES.filter(function(s) { return s[0] === e.shape; })[0] || ['', 'Shape'])[1];
  if (e.kind === 'gen') return (GENS.filter(function(g) { return g[0] === e.mode; })[0] || ['', '', 'Generated'])[2];
  if (e.kind === 'image') return 'Image';
  return KIND_NAME[e.kind] || e.kind;
}
function elIcon(e) {
  if (e.kind === 'shape') return '<span class="ly-ico">' + shapeSVG({ shape: e.shape, w: 14, h: 14, fill: e.fill || 'none', stroke: e.stroke || '#4b3fa8', strokeW: e.strokeW || (e.fill ? 0 : 1.5), radius: 3 }) + '</span>';
  var t = { text: 'T', data: e.as === 'image' ? IMG_ICON : DATA_ICON, image: IMG_ICON, gen: '#' }[e.kind] || '•';
  return '<span class="ly-ico">' + t + '</span>';
}
function renderLayers() {
  var btn = $('layersBtn'), box = $('layersPanel'); if (!btn || !box) return;
  btn.classList.toggle('on', _layersOpen);
  box.style.display = _layersOpen && layout ? 'block' : 'none';
  if (!_layersOpen || !layout) return;
  var list = entries().slice().reverse(), n = curEls().length, sel = selIdsNow(), gsel = selGroupId();
  var h = '<div class="ly-head"><b>Layers</b><span>' + (side === 'back' ? 'Back' : 'Front') + ' · ' + n + '</span>' +
          (sel.length > 1 && !gsel ? '<button type="button" class="ly-act" onclick="groupSel()" title="⌘G">Group ' + sel.length + '</button>' : '') +
          (gsel ? '<button type="button" class="ly-act" onclick="ungroupSel()" title="⇧⌘G">Ungroup</button>' : '') + '</div>';
  if (!n) h += '<div class="muted" style="padding:.4rem .2rem">Nothing on this side yet.</div>';
  h += '<div class="ly-list" id="lyList">';
  list.forEach(function(en) {
    if (en.group) {
      var g = groupById(en.group) || { name: 'Group' }, open = !_layerFold[en.group], all = en.els.every(function(x) { return sel.indexOf(x.id) >= 0; });
      h += '<div class="ly-row ly-group' + (all ? ' on' : '') + '" draggable="true" data-g="' + en.group + '">' +
           '<span class="ly-cb' + (all ? ' on' : '') + '" data-cb title="Select (with others)"></span>' + '<span class="ly-chev' + (open ? ' open' : '') + '" data-fold="' + en.group + '">▸</span>' +
           '<span class="ly-ico">▣</span><span class="ly-name" title="Double-click to rename">' + esc(g.name) + '</span><span class="ly-n">' + en.els.length + '</span></div>';
      if (open) en.els.slice().reverse().forEach(function(e) {
        h += '<div class="ly-row ly-child' + (sel.indexOf(e.id) >= 0 ? ' on' : '') + '" draggable="true" data-id="' + e.id + '" data-in="' + en.group + '">' + '<span class="ly-cb' + (sel.indexOf(e.id) >= 0 ? ' on' : '') + '" data-cb title="Select (with others)"></span>' + elIcon(e) + '<span class="ly-name">' + esc(elLabel(e)) + '</span><span class="ly-kind">' + KIND_NAME[e.kind] + '</span></div>';
      });
    } else {
      var e = en.el;
      h += '<div class="ly-row' + (sel.indexOf(e.id) >= 0 ? ' on' : '') + '" draggable="true" data-id="' + e.id + '">' + '<span class="ly-cb' + (sel.indexOf(e.id) >= 0 ? ' on' : '') + '" data-cb title="Select (with others)"></span>' + elIcon(e) + '<span class="ly-name">' + esc(elLabel(e)) + '</span><span class="ly-kind">' + KIND_NAME[e.kind] + '</span></div>';
    }
  });
  h += '</div><p class="ly-tip">Drag to reorder · tick the boxes (or Shift/⌘-click) to select several, then Group</p>';
  box.innerHTML = h;
}
// Clicks, renaming and drag-to-reorder in the Layers list
(function() {
  var box = $('layersPanel'); if (!box) return;
  box.addEventListener('click', function(ev) {
    var fold = ev.target.closest('[data-fold]');
    if (fold) { _layerFold[fold.dataset.fold] = !_layerFold[fold.dataset.fold]; renderLayers(); return; }
    var row = ev.target.closest('.ly-row'); if (!row) return;
    var now = Date.now(), plain = !(ev.shiftKey || ev.metaKey || ev.ctrlKey || ev.target.closest('[data-cb]'));
    if (row.dataset.g && plain && _lyClick.g === row.dataset.g && now - _lyClick.t < 450) { _lyClick = {}; box._startRename(row.dataset.g); return; }
    _lyClick = { g: row.dataset.g || '', t: now };
    var ids = row.dataset.g ? groupMembers(row.dataset.g).map(function(x) { return x.id; }) : [row.dataset.id];
    if (ev.shiftKey || ev.metaKey || ev.ctrlKey || ev.target.closest('[data-cb]')) {   // the checkbox adds to (or takes from) the selection
      var cur = selIdsNow(), has = ids.every(function(id) { return cur.indexOf(id) >= 0; });
      setSel(has ? cur.filter(function(id) { return ids.indexOf(id) < 0; }) : cur.concat(ids));
    } else setSel(ids);
  });
  // Double-click a group to rename it. A click redraws the list, and the browser won't send a
  // dblclick when the second click lands on a new row, so count the two clicks here.
  function startRename(gid) {
    var g = groupById(gid), row = box.querySelector('.ly-group[data-g="' + gid + '"]');
    var nm = row && row.querySelector('.ly-name'); if (!g || !nm) return;
    row.draggable = false;
    var inp = document.createElement('input'); inp.className = 'ly-rename'; inp.value = g.name; nm.replaceWith(inp); inp.focus(); inp.select();
    var finished = false;
    function done(save) { if (finished) return; finished = true; if (save) renameGroup(gid, inp.value); renderLayers(); }
    inp.addEventListener('click', function(c) { c.stopPropagation(); });
    inp.addEventListener('dblclick', function(c) { c.stopPropagation(); });
    inp.addEventListener('keydown', function(k) { k.stopPropagation(); if (k.key === 'Enter') done(true); if (k.key === 'Escape') done(false); });
    inp.addEventListener('blur', function() { done(true); });
  }
  box.addEventListener('dblclick', function(ev) { var hit = ev.target.closest('.ly-group'); if (hit && !box.querySelector('.ly-rename')) startRename(hit.dataset.g); });
  box._startRename = startRename;
  var drag = null;
  box.addEventListener('dragstart', function(ev) {
    var row = ev.target.closest('.ly-row'); if (!row) return;
    drag = { id: row.dataset.id, g: row.dataset.g, inG: row.dataset.in || '' };
    ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', 'layer');
    setTimeout(function() { row.classList.add('dragging'); }, 0);
  });
  function mark(ev) {
    box.querySelectorAll('.drop-before,.drop-after').forEach(function(x) { x.classList.remove('drop-before', 'drop-after'); });
    var row = ev.target.closest('.ly-row'); if (!row || !drag) return null;
    var r = row.getBoundingClientRect(), after = ev.clientY > r.top + r.height / 2;
    row.classList.add(after ? 'drop-after' : 'drop-before');
    return { row: row, after: after };
  }
  box.addEventListener('dragover', function(ev) { if (!drag) return; ev.preventDefault(); ev.dataTransfer.dropEffect = 'move'; mark(ev); });
  box.addEventListener('dragend', function() { drag = null; renderLayers(); });
  box.addEventListener('drop', function(ev) {
    if (!drag) return; ev.preventDefault();
    var m = mark(ev), d = drag; drag = null;
    if (!m) { renderLayers(); return; }
    var t = m.row.dataset, after = m.after;   // the list runs front to back, so "after" means lower
    if ((d.id && d.id === t.id) || (d.g && d.g === t.g)) { renderLayers(); return; }
    snapshot();
    var list = entries().slice().reverse();   // front to back, like the list
    function take() {   // remove the dragged thing from the list; returns its entry
      if (d.g) { var k = list.findIndex(function(en) { return en.group === d.g; }); return list.splice(k, 1)[0]; }
      var el = elById(d.id);
      for (var i = 0; i < list.length; i++) {
        if (list[i].el === el) return list.splice(i, 1)[0];
        if (list[i].group) { var j = list[i].els.indexOf(el); if (j >= 0) { list[i].els.splice(j, 1); return { el: el }; } }
      }
    }
    var moving = take();
    if (t.in && moving.el && (d.inG === t.in || !d.g)) {
      // into (or within) a group, next to one of its members
      var en = list.filter(function(x) { return x.group === t.in; })[0], tgt = elById(t.id);
      var front = en.els.slice().reverse(), pos = front.indexOf(tgt) + (after ? 1 : 0);
      front.splice(pos, 0, moving.el); en.els = front.reverse(); moving.el.group = t.in;
    } else {
      if (moving.el) delete moving.el.group;   // dragged out of its group
      var idx = list.findIndex(function(x) { return t.g ? x.group === t.g : (x.el && x.el.id === t.id) || (x.group && x.group === t.in); });
      if (idx < 0) idx = list.length;
      list.splice(idx + (after ? 1 : 0), 0, moving);
    }
    applyEntries(list.slice().reverse()); cleanGroups(); changed(true);
  });
})();

// Several elements, or a group, selected
function multiAttrs() {
  var sel = selEls(), gid = selGroupId(), g = gid && groupById(gid), bb = selBox(sel), h = '';
  h += '<p class="attr-title">' + (g ? esc(g.name) + ' <span class="kind">Group</span>' : sel.length + ' selected') + '</p>';
  if (g) h += '<span class="lbl">Name</span><input class="inp" value="' + esc(g.name) + '" onchange="renameGroup(\'' + gid + '\',this.value)">';
  h += '<span class="lbl">Position (pt)</span><div class="row2">' +
       '<div><span class="mini">X</span><input class="inp" type="number" step=".5" value="' + bb.x + '" onchange="moveSelTo(\'x\',this.value)"></div>' +
       '<div><span class="mini">Y</span><input class="inp" type="number" step=".5" value="' + bb.y + '" onchange="moveSelTo(\'y\',this.value)"></div></div>';
  h += '<p class="muted" style="margin:.35rem 0 0">' + r1(bb.w) + ' × ' + r1(bb.h) + ' pt. Drag on the card or use the arrow keys to move ' + (g ? 'the group' : 'them') + ' together.</p>';
  h += '<div class="attr-btns">' + (g ? '<button class="attr-btn" onclick="ungroupSel()">Ungroup</button>' : '<button class="attr-btn" onclick="groupSel()">Group</button>') + '</div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="reorderSel(1)">To front</button><button class="attr-btn" onclick="reorderSel(-1)">To back</button></div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="duplicateSel()">Duplicate</button><button class="attr-btn danger" onclick="deleteSel()">Delete</button></div>';
  h += '<span class="lbl">Shortcuts</span><p class="muted" style="margin:0">⌘G group · ⇧⌘G ungroup · ⌘A select all · Shift or ⌘-click to add to the selection</p>';
  return h;
}
function moveSelTo(axis, v) {
  v = parseFloat(v); if (isNaN(v)) return;
  var sel = selEls(), bb = selBox(sel); if (!bb) return; snapshot();
  var d = v - bb[axis]; sel.forEach(function(x) { x[axis] = r1(x[axis] + d); }); changed();
}

// ── Attributes panel ──────────────────────────────────────────────────────────
// Color pickers show the color on the card while you drag (liveColor); the change itself is
// made when the picker closes, as one undo step. Choosing a border color with no border width
// gives it a 1 pt border, so the color shows.
var _live = null;   // {obj, key, orig} while a picker is open
function liveTarget(onSet) { return onSet === 'setCard' ? layout.card : onSet === 'setBackProp' ? layout.back : elById(selId); }
function liveColor(onSet, key, val) {
  var o = liveTarget(onSet); if (!o) return;
  if (!_live || _live.obj !== o || _live.key !== key) _live = { obj: o, key: key, orig: o[key] };
  o[key] = val; render();
}
function endLive(o, key) { if (_live && _live.obj === o && _live.key === key) { o[key] = _live.orig; _live = null; } }
var STROKE_FOR = { borderColor: 'borderW', stroke: 'strokeW' };
function setProp(key, val, num) {
  var e = elById(selId); if (!e) return;
  endLive(e, key);
  if (num) { val = parseFloat(val); if (isNaN(val)) return; }
  if (e[key] === val) { render(); return; }
  snapshot(); e[key] = val;
  if (STROKE_FOR[key] && val && val !== 'transparent' && !(+e[STROKE_FOR[key]])) { e[STROKE_FOR[key]] = 1; changed(); return; }   // show the border
  if (key === 'bg' || key === 'color' || key === 'fill' || STROKE_FOR[key]) { changed(); return; }      // refresh the swatch and box
  changed(true);
}
function setBackProp(key, val) { endLive(layout.back, key); snapshot(); layout.back[key] = val; changed(); }
function setCard(key, val, num) {
  endLive(layout.card, key);
  if (num) { val = parseFloat(val); if (isNaN(val)) return; }
  snapshot(); layout.card[key] = val;
  if (key === 'borderColor' && val && val !== 'transparent' && !(+layout.card.borderW)) layout.card.borderW = 1;
  if (key === 'size') { var s = CARD_SIZES.filter(function(x) { return x.id === val; })[0]; if (s && s.w) { layout.card.w = s.w; layout.card.h = s.h; } }
  if (key === 'w' || key === 'h') layout.card.size = 'custom';
  changed(); if (key === 'size' || key === 'w' || key === 'h') fitZoom();
}
function syncGeomInputs(e) {
  ['x', 'y', 'w', 'h'].forEach(function(k) { var i = $('a_' + k); if (i && document.activeElement !== i) i.value = r1(e[k]); });
}
// A color field: a swatch that opens CardBoard's color chooser (transparent, a palette, recent
// colors, or any color), and a box to type a color.
function colorRow(label, key, val, none, onSet) {
  var clear = none ? (!val || val === 'transparent' || val === 'none') : val === 'transparent';   // empty text color means the default, not clear
  return '<span class="mini">' + label + '</span><div class="color-row">' +
    '<button type="button" class="swatch' + (clear ? ' clear' : '') + '" style="--c:' + esc(clear ? 'transparent' : (val || (none ? 'transparent' : '#111111'))) + '" title="Choose a color" aria-label="Choose a color"' +
      ' onclick="openColorPop(this,\'' + onSet + '\',\'' + key + '\',' + (none ? 'true' : 'false') + ')"></button>' +
    '<input class="inp" value="' + esc(val || '') + '" placeholder="' + (none ? 'none' : '#000000') + '" onchange="' + onSet + '(\'' + key + '\',this.value.trim())"></div>';
}
var PALETTE = ['#000000', '#3a3a3a', '#7a7a7a', '#bdbdbd', '#ffffff', '#fff8e8',
               '#2e2763', '#4b3fa8', '#7b6cf6', '#1a5f7a', '#2f80ed', '#56ccf2',
               '#1e7d4f', '#27ae60', '#a3d977', '#f2c14e', '#f2994a', '#eb5757',
               '#b0306b', '#e2557a', '#8e5b3a', '#c8a27a', '#765151', '#443131'];
function recentColors() { try { return JSON.parse(localStorage.getItem('cb_recentColors') || '[]'); } catch (e) { return []; } }
function rememberColor(v) {
  if (!/^#[0-9a-f]{6}$/i.test(v)) return;
  var r = recentColors().filter(function(x) { return x.toLowerCase() !== v.toLowerCase(); }); r.unshift(v);
  try { localStorage.setItem('cb_recentColors', JSON.stringify(r.slice(0, 12))); } catch (e) {}
}
function closeColorPop() { var p = $('colorPop'); if (p) p.remove(); }
function openColorPop(btn, onSet, key, none) {
  var open = $('colorPop') && $('colorPop').dataset.key === onSet + ':' + key; closeColorPop(); if (open) return;
  var target = liveTarget(onSet), cur = target ? String(target[key] || '') : '';
  var pick = function(v) { closeColorPop(); if (v && v !== 'transparent') rememberColor(v); window[onSet](key, v); };
  var tile = function(c) { return '<button type="button" class="cp-tile' + (cur.toLowerCase() === c.toLowerCase() ? ' on' : '') + '" style="--c:' + c + '" data-c="' + c + '" title="' + c + '"></button>'; };
  var rec = recentColors();
  var p = document.createElement('div'); p.id = 'colorPop'; p.className = 'color-pop'; p.dataset.key = onSet + ':' + key;
  p.innerHTML =
    '<button type="button" class="cp-clear' + ((none ? !cur || cur === 'transparent' : cur === 'transparent') ? ' on' : '') + '" data-c="' + (none ? '' : 'transparent') + '"><span class="cp-tile clear"></span>Transparent</button>' +
    '<div class="cp-grid">' + PALETTE.map(tile).join('') + '</div>' +
    (rec.length ? '<span class="mini">Recent</span><div class="cp-grid">' + rec.map(tile).join('') + '</div>' : '') +
    '<div class="cp-custom"><label class="cp-more"><input type="color" value="' + (/^#[0-9a-f]{6}$/i.test(cur) ? cur : '#7b6cf6') + '">Custom color…</label></div>';
  document.body.appendChild(p);
  var r = btn.getBoundingClientRect();
  p.style.left = Math.max(8, Math.min(r.left, innerWidth - p.offsetWidth - 8)) + 'px';
  p.style.top = (r.bottom + p.offsetHeight + 8 > innerHeight ? Math.max(8, r.top - p.offsetHeight - 6) : r.bottom + 6) + 'px';
  p.addEventListener('click', function(ev) { var t = ev.target.closest('[data-c]'); if (t) pick(t.dataset.c); });
  var nat = p.querySelector('input[type=color]');
  nat.addEventListener('input', function() { liveColor(onSet, key, nat.value); btn.style.setProperty('--c', nat.value); btn.classList.remove('clear'); });
  nat.addEventListener('change', function() { pick(nat.value); });
}
document.addEventListener('pointerdown', function(ev) { var p = $('colorPop'); if (p && !p.contains(ev.target) && !ev.target.closest('.swatch')) closeColorPop(); });
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeColorPop(); });
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
  h += '<div style="margin-top:.45rem">' + colorRow('Color', 'color', e.color, false, 'setProp') + '</div>';
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
  h += '<div style="margin-top:.45rem">' + colorRow('Border color', 'borderColor', e.borderColor, false, 'setProp') + '</div>';
  if (isTextLike(e)) h += '<div style="margin-top:.45rem">' + numInp('a_pad', 'Padding (pt)', e.pad == null ? 2 : e.pad, 'setProp', 'pad', .5) + '</div>';
  return h;
}
function fitAttrs(e) {
  return '<div style="margin-top:.45rem"><span class="mini">Fit</span>' + seg('fit', [['cover', 'Fill', 'Fill the box (crops)'], ['contain', 'Fit', 'Show the whole image'], ['stretch', 'Stretch', 'Stretch to the box']], e.fit || 'cover') + '</div>';
}
function renderAttrs() {
  var p = $('attrPanel'); renderLayers(); if (!layout) { p.innerHTML = ''; return; }
  if (multi.length) { p.innerHTML = multiAttrs(); return; }
  var e = elById(selId), h = '';
  if (!e) {
    var c = layout.card;
    h += '<p class="attr-title">' + (side === 'back' ? 'Card back' : 'Card') + '</p><p class="muted" style="margin:0 0 .4rem">Select something on the card to change it. ' +
         (side === 'back' ? 'Flip the card (top left) to work on the front.' : 'Flip the card (top left) to design its back.') + '</p>';
    if (side === 'back') h += '<span class="lbl">Back</span>' + colorRow('Background', 'bg', layout.back.bg, false, 'setBackProp') +
      '<p class="muted" style="margin:.3rem 0 0">' + (backVaries() ? 'This back changes from card to card, so exports include a back for every card.' : 'Every card shares this back.') + '</p>';
    h += '<span class="lbl">Size</span><select class="sel" onchange="setCard(\'size\',this.value)">' +
         CARD_SIZES.map(function(s) { return '<option value="' + s.id + '"' + (s.id === (c.size || 'custom') ? ' selected' : '') + '>' + s.name + '</option>'; }).join('') + '</select>';
    h += '<div class="row2" style="margin-top:.45rem">' +
         '<div><span class="mini">Width (' + unitName() + ')</span><input class="inp" type="number" step="' + unitStep() + '" value="' + toUnit(c.w) + '" onchange="setCard(\'w\',fromUnit(this.value),true)"></div>' +
         '<div><span class="mini">Height (' + unitName() + ')</span><input class="inp" type="number" step="' + unitStep() + '" value="' + toUnit(c.h) + '" onchange="setCard(\'h\',fromUnit(this.value),true)"></div></div>';
    h += '<span class="lbl">' + (side === 'back' ? 'Both sides' : 'Look') + '</span>' + (side === 'back' ? '' : colorRow('Background', 'bg', c.bg, false, 'setCard'));
    h += '<div class="row2" style="margin-top:.45rem">' + numInp('c_r', 'Corner radius (pt)', c.radius, 'setCard', 'radius') + numInp('c_bw', 'Border (pt)', c.borderW, 'setCard', 'borderW', .5) + '</div>';
    h += '<div style="margin-top:.45rem">' + colorRow('Border color', 'borderColor', c.borderColor, false, 'setCard') + '</div>';
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
    if (e.as === 'image') {
      var ru = ROWS[rowIdx] ? imageUrl(curSrc(e)) : '';
      h += '<span class="lbl">Picture for row ' + (rowIdx + 1) + '</span><div class="img-slot' + (UPLOADING[e.id] ? ' img-loading' : '') + '" id="imgSlot" onclick="openImagePicker(event,\'' + e.id + '\')" title="Click or drop a picture to change it">' +
        (ru ? '<div class="el-img" style="background-image:url(&quot;' + esc(ru) + '&quot;);background-size:contain"></div>' : '<div class="img-slot-empty">' + IMG_ICON + '<span>' + (ROWS[rowIdx] ? 'No picture in this row — drop one, or click to choose' : 'No rows yet') + '</span></div>') +
        '<span class="img-btn">' + IMG_ICON + '</span></div>' +
        '<p class="muted" style="margin:.35rem 0 0">Each row has its own picture, saved in its <b>' + esc(e.field) + '</b> cell. Click or drop a picture here, or double-click it on the card. Step through the rows to set the others.</p>';
    }
    h += e.as === 'image' ? fitAttrs(e) + boxAttrs(e, 'Frame') : textAttrs(e) + boxAttrs(e, 'Background');
  }
  if (e.kind === 'image') {
    var u = imageUrl(picRef(e.src)), src = e.src || '', uploaded = /\/images\/[A-Za-z0-9_\-]+\/[0-9a-f]{20}\.\w+$/.test(src);
    var from = !src ? '' : uploaded ? 'Uploaded file' : /dropbox\.com/i.test(src) ? 'Dropbox link' : /google\.com/i.test(src) ? 'Google Drive link' : 'Web link';
    var mapOn = !!(e.mapOn && e.map);
    h += '<span class="lbl">' + (mapOn ? 'Default picture' : 'Picture') + '</span><div class="img-slot' + (UPLOADING[e.id] ? ' img-loading' : '') + '" id="imgSlot" onclick="openImagePicker(event,\'' + e.id + '\')" title="Click or drop a picture to change it">' +
         '<button type="button" class="map-btn' + (mapOn ? ' on' : '') + '" onclick="event.stopPropagation();toggleImgMap()" title="' + (mapOn ? 'Stop choosing the picture by field value' : 'Choose the picture by a field\'s value') + '" aria-pressed="' + mapOn + '">[]</button>' +
         (u ? '<div class="el-img" style="background-image:url(&quot;' + esc(u) + '&quot;);background-size:contain"></div>' : '<div class="img-slot-empty">' + IMG_ICON + '<span>Drop a picture, or click to choose</span></div>') +
         '<span class="img-btn">' + IMG_ICON + '</span></div>';
    if (from) h += '<p class="muted" style="margin:.3rem 0 0">' + from + (uploaded ? '' : ' — loaded from the source, so changes there show up here') + '</p>';
    if (mapOn) h += '<div class="map-drawer" id="mapDrawer"><div class="map-drawer-in">' + mapAttrs(e) + '</div></div>';
    else h += '<p class="muted" style="margin:.3rem 0 0">The same picture on every card — a logo, frame or icon. Press <b>[ ]</b> to choose the picture by a field\'s value instead.</p>';
    h += fitAttrs(e) + boxAttrs(e, 'Frame');
  }
  if (e.kind === 'shape') {
    h += '<span class="lbl">Shape</span><select class="sel" onchange="setProp(\'shape\',this.value);renderAttrs()">' +
         SHAPES.map(function(s) { return '<option value="' + s[0] + '"' + (s[0] === e.shape ? ' selected' : '') + '>' + s[1] + '</option>'; }).join('') + '</select>';
    if (e.shape !== 'line') h += '<div style="margin-top:.45rem">' + colorRow('Fill', 'fill', e.fill, true, 'setProp') + '</div>';
    h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_sw', e.shape === 'line' ? 'Thickness (pt)' : 'Outline (pt)', e.strokeW, 'setProp', 'strokeW', .5) +
         (/^(rect|round)$/.test(e.shape) ? numInp('a_r', 'Corner radius', e.shape === 'round' && e.radius == null ? 10 : e.radius, 'setProp', 'radius') : '<div></div>') + '</div>';
    h += '<div style="margin-top:.45rem">' + colorRow(e.shape === 'line' ? 'Color' : 'Outline color', 'stroke', e.stroke, false, 'setProp') + '</div>';
  }
  if (e.kind === 'gen') {
    h += '<span class="lbl">Generate</span>' + seg('mode', [['seq', '123', 'Number sequence'], ['rand', DIE_ICON, 'Random number'], ['list', '☰', 'Random from a list']], e.mode);
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
    if (e.mode !== 'seq') h += '<div class="attr-btns"><button class="attr-btn" onclick="setProp(\'seed\',uid())">' + DIE_ICON + ' Re-roll</button></div>';
    h += textAttrs(e) + boxAttrs(e, 'Background');
  }
  h += '<span class="lbl">Position (pt)</span><div class="row4">' + numInp('a_x', 'X', e.x, 'setProp', 'x', .5) + numInp('a_y', 'Y', e.y, 'setProp', 'y', .5) + numInp('a_w', 'W', e.w, 'setProp', 'w', .5) + numInp('a_h', 'H', e.h, 'setProp', 'h', .5) + '</div>';
  h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_rot', 'Rotate (°)', e.rot || 0, 'setProp', 'rot', 1) +
       '<div><span class="mini">Opacity</span><input class="inp" type="number" min="0" max="100" step="5" value="' + Math.round((e.opacity == null ? 1 : e.opacity) * 100) + '" onchange="setProp(\'opacity\',clamp(this.value/100,0,1),true)"></div></div>';
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
  var dr = $('mapDrawer');
  if (dr && _mapAnim === 'open' && dr.animate && !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) dr.animate([{ height: '0px', opacity: 0 }, { height: dr.scrollHeight + 'px', opacity: 1 }], { duration: 260, easing: 'cubic-bezier(.2,.7,.3,1)' });
  _mapAnim = '';
  p.querySelectorAll('.map-slot').forEach(function(t) {
    var i = +t.dataset.i;
    t.addEventListener('dragover', function(d) { d.preventDefault(); t.classList.add('over'); });
    t.addEventListener('dragleave', function() { t.classList.remove('over'); });
    t.addEventListener('drop', function(d) {
      d.preventDefault(); t.classList.remove('over');
      var f = droppedFile(d.dataTransfer), l = droppedLink(d.dataTransfer);
      if (f) uploadImage(e.id, f, i); else if (l) setMapSrc(e.id, i, l);
    });
  });
}
// The image mapping part of an image element's panel: a field, then value → picture rows,
// always ending with an empty row for the next one
function mapAttrs(e) {
  var mp = e.map, items = mp.items || [], rows = items.concat([{ v: '', src: '' }]);
  var cur = mp.field && ROWS[rowIdx] ? cleanVal(ROWS[rowIdx][mp.field]).trim().toLowerCase() : null;
  var vals = [];   // the values the field has in the sheet, for the value boxes' lists
  if (mp.field) ROWS.forEach(function(r) { var v = cleanVal(r[mp.field]).trim(); if (v && vals.indexOf(v) < 0 && vals.length < 300) vals.push(v); });
  var h = '<span class="lbl">Field</span><input class="inp" list="mapFieldList" value="' + esc(mp.field || '') + '" placeholder="Choose a field" onchange="setMapField(this.value)">' +
    '<datalist id="mapFieldList">' + FIELDS.map(function(f) { return '<option value="' + esc(f) + '">'; }).join('') + '</datalist>' +
    '<datalist id="mapValList">' + vals.map(function(v) { return '<option value="' + esc(v) + '">'; }).join('') + '</datalist>';
  h += '<div class="map-list">';
  rows.forEach(function(it, i) {
    var u = imageUrl(picRef(it.src)), isNew = i === items.length, on = cur !== null && it.v && it.v.trim().toLowerCase() === cur;
    h += '<div class="map-item' + (isNew ? ' new' : '') + '">' +
      '<div class="img-slot map-slot' + (on ? ' cur' : '') + '" data-i="' + i + '" onclick="openImagePicker(event,\'' + e.id + '\',' + i + ')" title="Click or drop a picture">' +
        (u ? '<div class="el-img" style="background-image:url(&quot;' + esc(u) + '&quot;);background-size:contain"></div>' : '<div class="img-slot-empty">' + IMG_ICON + '<span>' + (isNew ? 'Picture for another value' : 'No picture — uses the link in ' + esc(mp.field || 'the field')) + '</span></div>') +
        (isNew ? '' : '<button type="button" class="map-x" onclick="event.stopPropagation();delMapItem(' + i + ')" title="Remove this value" aria-label="Remove this value">×</button>') +
        '<span class="img-btn">' + IMG_ICON + '</span></div>' +
      '<input class="inp map-val" list="mapValList" value="' + esc(it.v || '') + '" placeholder="' + (mp.field ? 'When ' + esc(mp.field) + ' is…' : 'Value') + '" onchange="setMapVal(' + i + ',this.value)">' +
      '</div>';
  });
  h += '</div><p class="muted" style="margin:.35rem 0 0">' + (mp.field
    ? 'A card whose <b>' + esc(mp.field) + '</b> matches a value shows its picture. Otherwise it shows the picture link in <b>' + esc(mp.field) + '</b>, or the default picture. Values and pictures can be tags like <code>[fire]</code>.'
    : 'Choose the field whose value picks the picture.') + '</p>';
  return h;
}
var _mapAnim = '';
function mapEl() { var e = elById(selId); return e && e.kind === 'image' && e.map ? e : null; }
function toggleImgMap() {
  var e = elById(selId); if (!e || e.kind !== 'image') return;
  var dr = $('mapDrawer');
  function flip() {
    snapshot(); e.mapOn = !e.mapOn;
    if (e.mapOn && !e.map) e.map = { field: '', items: [] };
    _mapAnim = e.mapOn ? 'open' : '';
    changed();
  }
  if (e.mapOn && dr && dr.animate && !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) {   // slide the drawer shut first
    dr.style.overflow = 'hidden';
    dr.animate([{ height: dr.offsetHeight + 'px', opacity: 1 }, { height: '0px', opacity: 0 }], { duration: 200, easing: 'ease-in', fill: 'forwards' }).onfinish = flip;
  } else flip();
}
function setMapField(v) { var e = mapEl(); if (!e || e.map.field === v.trim()) return; snapshot(); e.map.field = v.trim(); changed(); }
function setMapVal(i, v) {
  var e = mapEl(); if (!e) return; var items = e.map.items; v = v.trim();
  if (i >= items.length) { if (!v) return; snapshot(); items.push({ v: v, src: '' }); }
  else { if (items[i].v === v) return; snapshot(); items[i].v = v; }
  e.map.items = items.filter(function(it) { return it.v || it.src; });
  changed();
}
function setMapSrc(id, i, src) {
  var e = elById(id); if (!e || !e.map) return; var items = e.map.items;
  snapshot();
  if (i >= items.length) { if (!src) return; items.push({ v: '', src: src }); } else items[i].src = src;
  e.map.items = items.filter(function(it) { return it.v || it.src; });
  changed(selId !== id);
}
function delMapItem(i) { var e = mapEl(); if (!e) return; snapshot(); e.map.items.splice(i, 1); changed(); }
// Clicking outside the attribute panel (the card, the canvas, a layer) used to change the selection
// before the field's change event fired, so the typed text was lost. Commit the field first.
document.addEventListener('pointerdown', function(ev) {
  var a = document.activeElement, p = $('attrPanel');
  if (a && p && p.contains(a) && /^(INPUT|TEXTAREA)$/.test(a.tagName) && !p.contains(ev.target)) a.blur();
  if (a && a.isContentEditable && a.closest('.el.editing') && !a.contains(ev.target)) a.blur();   // editing a value on the card
}, true);
function liveText(v) {   // show typing on the card right away; one undo step when the box loses focus
  var e = elById(selId); if (!e) return;
  var node = $('cardHolder').querySelector('.el[data-id="' + e.id + '"] .el-inner');
  if (node) { var t = fill(v, ROWS[rowIdx]); node.innerHTML = t.trim() ? rich(t, {}) : ''; }
}
function insertField() {
  var f = $('a_ins').value, ta = $('a_text'); if (!f || !ta) return;
  var s = ta.selectionStart == null ? ta.value.length : ta.selectionStart, en = ta.selectionEnd == null ? s : ta.selectionEnd;
  ta.value = ta.value.slice(0, s) + '{' + f + '}' + ta.value.slice(en);
  setProp('text', ta.value); renderAttrs();
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
  // Printed cards are cut square, so draw them without rounded corners
  var sq = Object.assign({}, layout, { card: Object.assign({}, layout.card, { radius: 0 }) });

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
              '<div class="page" style="width:' + pw + 'pt;height:' + ph + 'pt;transform:scale(' + scale + ')">' +
              // Reference line, small: spreadsheet and sheet at the top left, page and side at the top right
              '<div class="page-ref left">' + esc((SHEET_TITLE || 'Spreadsheet') + ' · ' + curTab) + '</div>' +
              '<div class="page-ref right">Page ' + (backs === 'none' ? p + 1 : p * 2 + (isBack ? 2 : 1)) + ' of ' + (backs === 'none' ? nPages : nPages * 2) +
                (backs === 'none' ? '' : ' · ' + (isBack ? 'Back' : 'Front')) + '</div>';
      cards.forEach(function(it, i) {
        var col = i % cols, row = Math.floor(i / cols);
        if (isBack && backs === 'long') col = cols - 1 - col;   // flipped over the long edge: columns swap
        if (isBack && backs === 'short') row = rowsN - 1 - row; // flipped over the short edge: rows swap
        var cx = ox + col * (c.w + gap), cy = oy + row * (c.h + gap);
        html += '<div style="position:absolute;left:' + cx + 'pt;top:' + cy + 'pt;width:' + c.w + 'pt;height:' + c.h + 'pt' + (cut === 'outline' ? ';outline:.5pt solid #9aa0b8' : '') + '">' +
                renderCardHTML(sq, it.row, false, { card: p * per + i, row: it.ri, side: isBack ? 'back' : 'front' }) + '</div>';
      });
      if (!isBack) html += cropMarks(cards.length);
      html += '</div></div>';
    });
  }
  function cropMarks(count) {
    if (cut !== 'marks' || !count) return '';
    var out = '', used = Math.ceil(count / cols), len = clamp(Math.min(oy, ox) - 16, 3, 10),   // shorter marks when the cards come close to the edge, to clear the reference line
       xs = [], ys = [];
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

// Export settings from the bar (also stored with published links, so updates match)
function exportOpts() {
  return { target: $('eTarget').value, quality: +$('eQuality').value || 4096, copies: $('eCopies').value || '', round: $('eCorners').checked };
}
function listFor(copies) {
  var list = [];
  if (copies && FIELDS.indexOf(copies) < 0) copies = '';   // that column is gone: one of each
  ROWS.forEach(function(r, ri) {
    var n = copies ? parseInt(cleanVal(r[copies]), 10) : 1;
    if (isNaN(n)) n = 0;
    for (var i = 0; i < n; i++) list.push({ row: r, ri: ri });
  });
  return list;
}
// How the cards fall onto sheets for the chosen target
function exportPlan(o, lay) {
  lay = lay || layout;
  var t = EXPORT_TARGETS[o.target] || EXPORT_TARGETS.tts, c = lay.card;
  var list = listFor(o.copies), n = list.length, maxPx = o.quality || 4096;
  if (t.single) {
    var w1 = Math.round(Math.min(maxPx / 4, 1500));
    return { o: o, t: t, list: list, single: true, cardW: w1, cardH: Math.round(w1 * c.h / c.w), round: o.round, sheets: [] };
  }
  var slots = n + (t.backLast ? 1 : 0), cols, rows, cardW;
  if (o.grid && o.grid.cols) {   // published links keep their grid, so the game's settings stay right
    cols = o.grid.cols; rows = o.grid.rows;
  } else if (t.maxCols && o.fixed) {   // first publish for TTS: the full 10 × 7, with room to grow
    cols = t.maxCols; rows = t.maxRows;
  } else if (t.maxCols) {               // TTS: up to 10 × 7 per sheet, the last slot is the hidden-card image
    cols = Math.max(2, Math.min(t.maxCols, slots)); rows = Math.max(2, Math.min(t.maxRows, Math.ceil(slots / cols)));
  } else {                       // Screentop: as square a grid as fits the size limit
    var ratio = c.h / c.w; cols = Math.max(1, Math.ceil(Math.sqrt(slots * ratio))); rows = Math.ceil(slots / cols);
    while (cols * rows < slots) rows++;
    var minW = 150;              // keep cards legible; overflow goes onto more sheets
    cols = Math.min(cols, Math.floor(maxPx / minW)); rows = Math.min(rows, Math.floor(maxPx / (minW * ratio)));
  }
  cardW = Math.floor(Math.min(maxPx / cols, maxPx / rows * c.w / c.h));
  var per = cols * rows - (t.backLast ? 1 : 0), sheets = [];
  for (var i = 0; i < Math.max(1, Math.ceil(n / per)); i++) sheets.push(list.slice(i * per, (i + 1) * per));
  return { o: o, t: t, list: list, cols: cols, rows: rows, per: per, cardW: cardW, cardH: Math.round(cardW * c.h / c.w), sheets: sheets, round: o.round };
}
function exportLayout(round) {   // a frozen copy, so editing during a background update can't mix in
  var lay = JSON.parse(JSON.stringify(layout));
  if (!round) lay.card.radius = 0;
  return lay;
}

function renderExport() {
  var box = $('exportPreview'); if (!layout) { box.innerHTML = ''; return; }
  cardList($('eCopies'), renderExport);   // fills the Copies choices
  var o = exportOpts(), pub = (PUBLISHED[curTab] || {})[o.target];
  if (pub && pub.grid && pub.settings && pub.settings.quality === o.quality) o.grid = pub.grid;   // match the published links
  var P = exportPlan(o), c = layout.card, varies = backVaries();
  $('eBackNote').innerHTML = varies ? '<b>Unique backs</b>&nbsp;— the back has per-card fields' : 'One back for every card';
  var info;
  if (P.single) info = P.list.length + ' cards → ' + P.list.length + ' images + back, ' + P.cardW + ' × ' + P.cardH + ' px each';
  else info = P.list.length + ' cards → ' + P.sheets.length + (P.sheets.length === 1 ? ' sheet' : ' sheets') + ' of ' + P.cols + ' × ' + P.rows +
             ' · ' + (P.cols * P.cardW) + ' × ' + (P.rows * P.cardH) + ' px · ' + P.cardW + ' × ' + P.cardH + ' px per card';
  $('exportInfo').textContent = info;
  $('exportHelp').innerHTML = exportHelp(P);
  renderLinks();
  // Preview (HTML, scaled down) — the download draws the real pictures
  var h = '', cw = c.w * PX, chh = c.h * PX, lay = exportLayout(P.round);
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
  var tgt = P.o.target, uniq = backVaries();
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
function drawCard(html, w, c) {
  var holder = $('exportStage');
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
// Published links use JPEG (a fraction of the size) unless rounded corners need transparency
function fileBlob(cv, o) {
  if (!o.jpeg) return canvasBlob(cv);
  var w = document.createElement('canvas'); w.width = cv.width; w.height = cv.height;
  var g = w.getContext('2d'); g.fillStyle = '#ffffff'; g.fillRect(0, 0, w.width, w.height); g.drawImage(cv, 0, 0);
  return canvasBlob(w, 'image/jpeg');
}

// Draw every file for an export. onFile(name, blob, info) is called for each and may return a promise.
// Only one export draws at a time (they share the off-screen stage), so calls queue up.
var _drawQueue = Promise.resolve();
function buildExport(o, onFile, tick) {
  if (_rowsPartial) return Promise.reject(new Error('the rows are still loading — try again in a moment'));
  var run = _drawQueue.then(function() { return buildExportNow(o, onFile, tick || function() {}); });
  _drawQueue = run.catch(function() {});
  return run;
}
function buildExportNow(o, onFile, tick) {
  var lay = exportLayout(o.round), P = exportPlan(o, lay), c = lay.card, uniq = backVaries(lay), ext = o.jpeg ? '.jpg' : '.png';
  function blob(cv) { return fileBlob(cv, o); }
  var total = P.list.length * (uniq ? 2 : 1) + 1, done = 0, back, files = [];
  function step(msg) { tick(done / total, msg); }
  function emit(name, blob, info) { files.push(Object.assign({ name: name }, info || {})); return onFile(name, blob, info); }
  if (!P.list.length) return Promise.reject(new Error('No cards to export'));
  step('Loading…');
  return exportLibs()
    .then(function() {
      if (uniq) return;
      return drawCard(backHTML(P.list[0], 0, { lay: lay, proxy: true }), P.cardW, c)
        .then(function(cv) { back = cv; done++; return blob(cv); }).then(function(b) { return emit('back' + ext, b, { kind: 'back' }); });
    })
    .then(function() {
      var chain = Promise.resolve(), index = 0;
      if (P.single) {
        P.list.forEach(function(it, i) {
          chain = chain.then(function() {
            step('Drawing card ' + (i + 1) + ' of ' + P.list.length + '…');
            var nm = 'card-' + String(i + 1).padStart(3, '0');
            return drawCard(renderCardHTML(lay, it.row, false, { card: i, row: it.ri, proxy: true }), P.cardW, c)
              .then(blob).then(function(b) { done++; return emit(nm + ext, b, { kind: 'card' }); })
              .then(function() {
                if (uniq) return drawCard(backHTML(it, i, { lay: lay, proxy: true }), P.cardW, c).then(blob)
                  .then(function(b) { done++; return emit(nm + '-back' + ext, b, { kind: 'card-back' }); });
              });
          });
        });
        return chain;
      }
      var prefix = o.target === 'tts' ? 'faces-' : 'sheet-';
      P.sheets.forEach(function(cards, si) {
        chain = chain.then(function() {
          var sheet = document.createElement('canvas'), bsheet = document.createElement('canvas');
          sheet.width = bsheet.width = P.cols * P.cardW; sheet.height = bsheet.height = P.rows * P.cardH;
          var g = sheet.getContext('2d'), bg = bsheet.getContext('2d'), hidden = null, inner = Promise.resolve();
          cards.forEach(function(it, i) {
            var k = index++;
            inner = inner.then(function() {
              step('Sheet ' + (si + 1) + ': card ' + (i + 1) + ' of ' + cards.length + '…');
              var x = (i % P.cols) * P.cardW, y = Math.floor(i / P.cols) * P.cardH;
              return drawCard(renderCardHTML(lay, it.row, false, { card: k, row: it.ri, proxy: true }), P.cardW, c)
                .then(function(cv) { g.drawImage(cv, x, y); done++; })
                .then(function() {
                  if (uniq) return drawCard(backHTML(it, k, { lay: lay, proxy: true }), P.cardW, c).then(function(cv) { bg.drawImage(cv, x, y); if (!hidden) hidden = cv; done++; });
                });
            });
          });
          var info = { kind: 'faces', sheet: si + 1, cards: cards.length };
          return inner.then(function() {
            var hx = (P.cols - 1) * P.cardW, hy = (P.rows - 1) * P.cardH;
            if (P.t.backLast) { g.drawImage(uniq ? hidden : back, hx, hy); if (uniq) bg.drawImage(hidden, hx, hy); }
            return blob(sheet);
          }).then(function(b) { return emit(prefix + (si + 1) + ext, b, info); })
            .then(function() { if (uniq) return blob(bsheet).then(function(b2) { return emit('backs-' + (si + 1) + ext, b2, { kind: 'backs', sheet: si + 1, cards: cards.length }); }); });
        });
      });
      return chain;
    })
    .then(function() { $('exportStage').innerHTML = ''; tick(1, ''); return { P: P, files: files, uniq: uniq }; },
          function(e) { $('exportStage').innerHTML = ''; throw e; });
}

function exportBusy(on, btn) {
  _exporting = on; $('exportGo').disabled = on; $('publishGo').disabled = on;
  $('exportProgress').style.display = on ? 'block' : 'none';
  if (!on) $('exportStatus').textContent = '';
}
function exportTick(f, msg) { $('exportProgress').firstChild.style.width = Math.round(f * 100) + '%'; $('exportStatus').textContent = msg; }

function runExport() {
  if (_exporting || !layout) return;
  if (!requirePro('export', runExport)) return;
  var o = exportOpts(), zip, pub = (PUBLISHED[curTab] || {})[o.target];
  if (pub && pub.grid && pub.settings && pub.settings.quality === o.quality) o.grid = pub.grid;
  exportBusy(true);
  exportLibs()
    .then(function() { zip = new JSZip(); return buildExport(o, function(name, blob) { zip.file(name, blob); }, exportTick); })
    .then(function(r) {
      exportTick(1, 'Packing…');
      zip.file('README.txt', exportHelp(r.P).replace(/<[^>]+>/g, '') + '\n\nMade with CardBoard — zapsheets.com\n');
      return zip.generateAsync({ type: 'blob' }).then(function(blob) {
        var a = document.createElement('a'), name = (curTab || 'cards').replace(/[^\w\- ]+/g, '').trim() || 'cards';
        a.href = URL.createObjectURL(blob); a.download = name + ' - ' + EXPORT_TARGETS[o.target].name + '.zip';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function() { URL.revokeObjectURL(a.href); }, 60000);
        toast('✓ Exported ' + r.P.list.length + ' cards');
      });
    })
    .catch(function(e) { toast('Export failed — ' + e.message, true); })
    .then(function() { exportBusy(false); });
}

// ── Links: sheets hosted on our server at addresses that never change ────────
// Publishing uploads the same file names each time, so a link pasted into
// Tabletop Simulator shows the newest cards. Saving the layout or reloading the
// sheet data updates every published set for that sheet tab.
var PUBLISHED = {};   // tab → {target: manifest}
function publishApi(action, extra, file) {
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('action', action);
  Object.keys(extra || {}).forEach(function(k) { fd.append(k, extra[k]); });
  if (file) fd.append('file', file, extra.name);
  return cbPost(APP_BASE + 'push/cardboardPublish.php', fd)
    .then(function(r) { if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response'); return r; });
}
function loadPublished(tab) {
  return publishApi('list', { tab: tab }).then(function(r) {
    PUBLISHED[tab] = r.published || {};
    if (curTab === tab && _activeTab === 'export') renderLinks();
  }).catch(function() {});
}
function publishSet(tab, o, tick) {
  if (!hasAccess()) return Promise.reject(new Error('your free week has ended'));
  return buildExport(o, function(name, blob) { return publishApi('upload', { tab: tab, target: o.target, name: name }, blob); }, tick)
    .then(function(r) {
      var st = Object.assign({}, o); delete st.grid;
      var man = { files: r.files, count: r.P.list.length, grid: r.P.single ? null : { cols: r.P.cols, rows: r.P.rows, uniqueBacks: r.uniq }, settings: st };
      return publishApi('finish', { tab: tab, target: o.target, manifest: JSON.stringify(man) });
    })
    .then(function(r) { (PUBLISHED[tab] = PUBLISHED[tab] || {})[o.target] = r.published; return r.published; });
}
function runPublish(asked) {
  if (_exporting || !layout) return;
  if (!requirePro('links', runPublish)) return;
  var o = Object.assign(exportOpts(), { fixed: true }), tab = curTab, old = (PUBLISHED[tab] || {})[o.target];
  o.jpeg = !o.round;
  if (old && old.grid && old.settings && old.settings.quality === o.quality) o.grid = old.grid;   // keep the grid the game was set up with
  if (dirty && asked !== true) {
    askDialog({ title: 'Publish unsaved changes?', message: 'Your layout has changes you haven’t saved. The links will show them too.', ok: 'Publish', cancel: 'Cancel' })
      .then(function(yes) { if (yes) runPublish(true); });
    return;
  }
  exportBusy(true);
  publishSet(tab, o, exportTick)
    .then(function() { toast('✓ Links ready — they\'ll update when you save'); if (curTab === tab) renderExport(); })
    .catch(function(e) { toast('Couldn\'t publish — ' + e.message, true); })
    .then(function() { exportBusy(false); });
}
function updateLinksNow(target) {
  var m = (PUBLISHED[curTab] || {})[target]; if (!m || _exporting) return;
  var tab = curTab; exportBusy(true);
  publishSet(tab, Object.assign({}, m.settings || {}, { target: target, grid: m.grid }), exportTick)
    .then(function() { toast('✓ Links updated'); if (curTab === tab) renderLinks(); })
    .catch(function(e) { toast('Couldn\'t update — ' + e.message, true); })
    .then(function() { exportBusy(false); });
}
function stopSharing(target, asked) {
  if (!asked) {
    askDialog({ title: 'Stop sharing these links?', message: 'The files are removed from the server and the links stop working. Anything using them — like a Tabletop Simulator deck — will lose its pictures.',
                ok: 'Stop sharing', cancel: 'Keep links', danger: true })
      .then(function(yes) { if (yes) stopSharing(target, true); });
    return;
  }
  var tab = curTab;
  publishApi('remove', { tab: tab, target: target }).then(function() {
    if (PUBLISHED[tab]) delete PUBLISHED[tab][target];
    toast('Links removed'); renderLinks();
  }).catch(function(e) { toast('Couldn\'t remove — ' + e.message, true); });
}
// After a save or a data reload: refresh every published set for that tab, quietly
var _autoPending = {};
function autoRepublish(tab) {
  var sets = PUBLISHED[tab]; if (!sets || curTab !== tab) return;
  if (_rowsPartial) { _republishWaiting = tab; return; }   // wait for every row, or the links would get only the first cards
  Object.keys(sets).forEach(function(target) {
    if (_autoPending[tab + ':' + target]) return;
    _autoPending[tab + ':' + target] = true;
    setLinkStatus('Updating links…');
    publishSet(tab, Object.assign({}, sets[target].settings || {}, { target: target, grid: sets[target].grid }))
      .then(function() { setLinkStatus(''); toast('✓ ' + EXPORT_TARGETS[target].name + ' links updated'); if (curTab === tab && _activeTab === 'export') renderLinks(); })
      .catch(function(e) { setLinkStatus(''); toast('Couldn\'t update the ' + EXPORT_TARGETS[target].name + ' links — ' + e.message, true); })
      .then(function() { delete _autoPending[tab + ':' + target]; });
  });
}
function setLinkStatus(msg) { var st = $('saveStatus'); if (!st) return; if (msg) { st.dataset.prev = st.textContent; st.textContent = msg; } else if (st.dataset.prev != null) { st.textContent = dirty ? 'Unsaved changes' : 'Saved'; delete st.dataset.prev; } }
function copyLink(btn) {
  var inp = btn.previousElementSibling; inp.select();
  (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject()).catch(function() { document.execCommand('copy'); });
  btn.textContent = 'Copied'; setTimeout(function() { btn.textContent = 'Copy'; }, 1400);
}
function renderLinks() {
  var box = $('exportLinks'); if (!box) return;
  var target = $('eTarget').value, m = (PUBLISHED[curTab] || {})[target];
  $('publishGo').innerHTML = m ? 'Republish links' : 'Get links';
  if (!m) { box.innerHTML = ''; box.style.display = 'none'; return; }
  var when = new Date(m.updated), g = m.grid || {};
  function row(label, f, extra) {
    return '<div class="lk-row"><span class="lk-name">' + label + '</span><input class="inp" readonly value="' + esc(f.url) + '" onclick="this.select()"><button type="button" class="attr-btn" onclick="copyLink(this)">Copy</button><span class="lk-meta' + (extra ? '' : ' empty') + '">' + (extra || '') + '</span></div>';
  }
  var h = '<div class="lk-head"><b>Links</b> · ' + m.count + ' cards · updated ' + esc(when.toLocaleString()) +
          ' <button type="button" class="lk-act" onclick="updateLinksNow(\'' + target + '\')">Update now</button><button type="button" class="lk-act danger" onclick="stopSharing(\'' + target + '\')">Stop sharing</button></div>';
  // Unique backs: on when the back layout has something that changes per card (a Data field, {Column} text, a generated value)
  var pubUniq = !!g.uniqueBacks || m.files.some(function(f) { return f.kind === 'backs' || f.kind === 'card-back'; }), nowUniq = backVaries();
  h += '<div class="lk-backs ' + (pubUniq ? 'on' : 'off') + '"><b>Unique backs: ' + (pubUniq ? 'On' : 'Off') + '</b> — ' +
       (pubUniq ? 'the back has fields that change from card to card, so every card gets its own back' + (target === 'tts' ? ' (tick <b>Unique backs</b> in TTS and use the backs link)' : '')
                : 'the back is the same on every card, so there\'s one back picture' + (target === 'tts' ? ' (leave <b>Unique backs</b> unticked in TTS)' : '')) + '.' +
       (pubUniq !== nowUniq ? ' <span class="lk-warn">The back layout now ' + (nowUniq ? 'has' : 'no longer has') + ' per-card fields — this switches to ' + (nowUniq ? 'On' : 'Off') + ' with the next update, and the TTS deck will need its Back link and Unique backs setting changed.</span>' : '') + '</div>';
  var faces = m.files.filter(function(f) { return f.kind === 'faces'; }), backs = m.files.filter(function(f) { return f.kind === 'backs'; }), back = m.files.filter(function(f) { return f.kind === 'back'; })[0];
  if (faces.length) {
    faces.forEach(function(f) {
      var b = backs.filter(function(x) { return x.sheet === f.sheet; })[0];
      h += row((faces.length > 1 ? 'Sheet ' + f.sheet + ' face' : 'Face'), f, target === 'tts' ? 'Width ' + g.cols + ' · Height ' + g.rows + ' · Number ' + f.cards : '');
      if (b) h += row((faces.length > 1 ? 'Sheet ' + f.sheet + ' backs' : 'Backs'), b, target === 'tts' ? 'Unique backs ✓' : '');
    });
    if (back) h += row('Back', back, target === 'tts' ? 'Same back for all' : '');
  } else {
    h += '<div class="muted">' + m.files.length + ' pictures — the first ones:</div>';
    m.files.slice(0, 4).forEach(function(f) { h += row(esc(f.name), f); });
  }
  if (target === 'tts') h += '<p class="muted lk-note">Paste these into the Custom Deck dialog instead of choosing files. When you save the layout or reload the sheet data here, the pictures at these links are replaced. ' +
       'Tabletop Simulator keeps its own copy of downloaded pictures, so a game may show the old cards until TTS fetches them again. ' +
       'If you add cards, raise Number in TTS to match; past ' + (g.cols * g.rows - 1) + ' cards a new sheet (and link) is added.</p>';
  else h += '<p class="muted lk-note">These links stay the same; saving the layout or reloading the sheet data replaces the pictures behind them.</p>';
  box.innerHTML = h; box.style.display = 'block';
}

// ── Data tab: the sheet as a grid you can edit ───────────────────────────────
// Cells show what's typed in the sheet (formulas too). An edit is written to
// that one cell as if typed in Sheets; if someone changed the cell in the
// meantime, nothing is overwritten and the new value is shown instead.
var GRID = null, _gridSel = null, _gridEdit = null, _rowsStale = false, _gridFilter = '';
function loadGrid(force) {
  var tab = curTab, box = $('gridWrap');
  if (!tab) return;
  if (GRID && GRID.tab === tab && !force) { renderGrid(); return; }
  box.innerHTML = '<div class="loading"><span class="spin"></span>Loading “' + esc(tab) + '”…</div>';
  api('grid', { tab: tab }).then(function(r) {
    if (curTab !== tab) return;
    GRID = { tab: tab, header: r.header || [], rows: r.rows || [] };
    _gridSel = null; renderGrid();
  }).catch(function(e) { box.innerHTML = '<div class="muted" style="padding:2rem">Couldn\'t read the sheet — ' + esc(e.message) + '</div>'; });
}
function gridCols() { return GRID.header.map(function(h, i) { return { name: h, i: i }; }).filter(function(c) { return c.name && !isHidden(c.name); }); }
function gridVisibleRows() {
  if (!_gridFilter) return GRID.rows;
  var f = _gridFilter.toLowerCase();
  return GRID.rows.filter(function(r) { return r.draft || r.values.some(function(v) { return String(v).toLowerCase().indexOf(f) >= 0; }); });
}
function renderGrid() {
  var box = $('gridWrap'); if (!GRID) return;
  var cols = gridCols(), rows = gridVisibleRows();
  var nHid = GRID.header.filter(function(hd) { return hd && isHidden(hd); }).length;
  $('gridInfo').innerHTML = GRID.rows.filter(function(r) { return !r.draft; }).length + ' rows · ' + cols.length + ' columns' + (_gridFilter ? ' · ' + rows.length + ' shown' : '') +
    (nHid ? ' · ' + nHid + ' hidden <button type="button" class="lk-act" onclick="showAllFields()">Show all</button>' : '');
  if (!cols.length) { box.innerHTML = '<div class="muted" style="padding:2rem">This sheet has no column headings yet. Add one with <b>New</b> in the Design tab’s Data list.</div>'; return; }
  var imgCols = {};
  cols.forEach(function(c) { imgCols[c.i] = FIELDS.indexOf(c.name) >= 0 && looksLikeImage(c.name); });
  var h = '<table class="grid"><thead><tr><th class="rn"></th>' + cols.map(function(c) { return '<th data-c="' + c.i + '" title="Click for options">' + esc(c.name) + '<span class="th-caret">▾</span></th>'; }).join('') + '</tr></thead><tbody>';
  rows.forEach(function(r) {
    var ri = GRID.rows.indexOf(r);
    h += '<tr data-r="' + ri + '"' + (r.draft ? ' class="draft"' : '') + '><td class="rn">' + (r.draft ? '+' : r.row) + '</td>';
    cols.forEach(function(c) {
      var v = r.values[c.i] || '', st = (r.state || {})[c.i] || '';
      var thumb = imgCols[c.i] && imageUrl(v) ? '<img class="g-thumb" src="' + esc(imageUrl(v)) + '" alt="" referrerpolicy="no-referrer">' : '';
      h += '<td data-r="' + ri + '" data-c="' + c.i + '" class="' + (st ? 'st-' + st : '') + (/^=/.test(v) ? ' formula' : '') + '" title="' + esc(v.length > 60 ? v : '') + '">' + thumb + '<span>' + esc(v) + '</span></td>';
    });
    h += '</tr>';
  });
  h += '</tbody></table>';
  if (!rows.length) h += '<div class="muted" style="padding:1rem">No rows' + (_gridFilter ? ' match “' + esc(_gridFilter) + '”' : '') + '.</div>';
  var sx = box.scrollLeft, sy = box.scrollTop;
  box.innerHTML = h; box.scrollLeft = sx; box.scrollTop = sy;
  if (_gridSel) markGridSel();
}
function gridCell(r, c) { return $('gridWrap').querySelector('td[data-r="' + r + '"][data-c="' + c + '"]'); }
function markGridSel() {
  var old = $('gridWrap').querySelector('td.sel'); if (old) old.classList.remove('sel');
  var td = _gridSel && gridCell(_gridSel.r, _gridSel.c); if (td) { td.classList.add('sel'); td.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
}
function selectGridCell(r, c) { _gridSel = { r: r, c: c }; markGridSel(); }
function moveGridSel(dr, dc) {
  if (!_gridSel) return;
  var rows = gridVisibleRows().map(function(x) { return GRID.rows.indexOf(x); }), cols = gridCols().map(function(x) { return x.i; });
  var ri = rows.indexOf(_gridSel.r), ci = cols.indexOf(_gridSel.c);
  ri = clamp(ri + dr, 0, rows.length - 1); ci = clamp(ci + dc, 0, cols.length - 1);
  selectGridCell(rows[ri], cols[ci]);
}
function startGridEdit(typed) {
  if (!_gridSel || _gridEdit) return;
  if (!requirePro('edit', function() {})) return;
  var td = gridCell(_gridSel.r, _gridSel.c); if (!td) return;
  var r = GRID.rows[_gridSel.r], v = r.values[_gridSel.c] || '';
  var rect = td.getBoundingClientRect(), wrap = $('gridWrap').getBoundingClientRect();
  var ta = document.createElement('textarea');
  ta.className = 'g-editor'; ta.value = typed != null ? typed : v;
  ta.style.left = (rect.left - wrap.left + $('gridWrap').scrollLeft) + 'px'; ta.style.top = (rect.top - wrap.top + $('gridWrap').scrollTop) + 'px';
  ta.style.minWidth = Math.max(rect.width, 180) + 'px'; ta.style.minHeight = rect.height + 'px';
  $('gridWrap').appendChild(ta);
  _gridEdit = { r: _gridSel.r, c: _gridSel.c, ta: ta, old: v };
  ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
  function fit() { ta.style.height = 'auto'; ta.style.height = Math.min(260, ta.scrollHeight + 2) + 'px'; }
  fit(); ta.addEventListener('input', fit);
  ta.addEventListener('keydown', function(ev) {
    ev.stopPropagation();   // the grid's own keys (Enter = edit, arrows = move) mustn't see these
    if (ev.key === 'Escape') { ev.preventDefault(); endGridEdit(false); }
    else if (ev.key === 'Enter' && !ev.shiftKey && !ev.altKey) { ev.preventDefault(); endGridEdit(true); moveGridSel(1, 0); }   // Shift/Alt+Enter: new line
    else if (ev.key === 'Tab') { ev.preventDefault(); endGridEdit(true); moveGridSel(0, ev.shiftKey ? -1 : 1); }
  });
  ta.addEventListener('blur', function() { if (_gridEdit && _gridEdit.ta === ta) endGridEdit(true); });
}
function endGridEdit(save) {
  var ed = _gridEdit; if (!ed) return;
  _gridEdit = null;
  var val = ed.ta.value; ed.ta.remove();
  if (save && val !== ed.old) saveGridCell(ed.r, ed.c, val, ed.old);
}
function setCellState(r, c, st) { var row = GRID.rows[r]; (row.state = row.state || {})[c] = st; var td = gridCell(r, c); if (td) { td.className = td.className.replace(/\bst-\w+/g, '').trim() + (st ? ' st-' + st : ''); } }
function paintCell(r, c) {
  var td = gridCell(r, c); if (!td) return;
  var v = GRID.rows[r].values[c] || '';
  td.querySelector('span').textContent = v;
  td.classList.toggle('formula', /^=/.test(v));
}
function saveGridCell(r, c, val, old) {
  var row = GRID.rows[r], field = GRID.header[c], tab = GRID.tab;
  row.values[c] = val; paintCell(r, c); setCellState(r, c, 'saving');
  var req = row.draft
    ? api('addrow', { tab: tab, values: JSON.stringify((function() { var o = {}; GRID.header.forEach(function(h, i) { if (h && row.values[i]) o[h] = row.values[i]; }); return o; })()) })
        .then(function(res) { row.draft = false; row.row = res.row; renderGrid(); return { value: val }; })
    : api('setcell', { tab: tab, row: row.row, field: field, value: val, old: old });
  req.then(function(res) {
    _rowsStale = true;
    if (res && res.value != null && !row.draft) { row.values[c] = res.value; paintCell(r, c); }
    setCellState(r, c, 'saved');
    setTimeout(function() { if ((row.state || {})[c] === 'saved') setCellState(r, c, ''); }, 1200);
  }).catch(function(e) {
    if (e.message === 'conflict') {
      // Someone changed this cell in the sheet: keep their value
      api('grid', { tab: tab }).then(function(g) {
        var fresh = (g.rows || []).filter(function(x) { return x.row === row.row; })[0];
        if (fresh) { row.values = fresh.values; renderGrid(); }
      });
      setCellState(r, c, 'error');
      toast('That cell was changed in the sheet since you loaded it — showing the new value', true);
    } else {
      row.values[c] = old; paintCell(r, c); setCellState(r, c, 'error');
      toast('Couldn\'t save — ' + e.message, true);
    }
  });
}
function addGridRow() {
  if (!GRID) return;
  var n = GRID.header.length, r = { row: 0, draft: true, values: new Array(n).fill('') };
  GRID.rows.push(r); _gridFilter = ''; $('gridFilter').value = ''; renderGrid();
  var first = gridCols()[0]; if (first) { selectGridCell(GRID.rows.length - 1, first.i); startGridEdit(); }
}
function filterGrid(v) { _gridFilter = v.trim(); renderGrid(); }
// + Add column (Data tab): a new heading in the sheet, ready to fill in
function gridAddedColumn(n) {   // keep the grid in step when a column is added from either tab
  if (!GRID || GRID.tab !== curTab || GRID.header.indexOf(n) >= 0) return;
  var at = GRID.header.length;
  while (at > 0 && !GRID.header[at - 1]) at--;   // first slot after the last heading
  GRID.header[at] = n; GRID.rows.forEach(function(r) { while (r.values.length < GRID.header.length) r.values.push(''); });
  if (_activeTab === 'data') renderGrid();
}
function showAddColumn() {
  var box = $('gridAddCol'); box.classList.add('open');
  var inp = $('gridAddColName'); inp.value = ''; inp.disabled = false; $('gridAddColMsg').textContent = ''; inp.focus();
}
function hideAddColumn() { var box = $('gridAddCol'); if (box) box.classList.remove('open'); $('gridAddColMsg').textContent = ''; }
var _addingCol = false;
function addColumn(ev) {
  if (ev) ev.preventDefault();
  if (!hasAccess()) { requirePro('edit', function() {}); return; }
  var box = $('gridAddCol'), inp = $('gridAddColName');
  if (_addingCol || !box.classList.contains('open')) return;
  var n = inp.value.trim().replace(/\s+/g, ' ');
  if (!n) { hideAddColumn(); return; }
  var known = (GRID ? GRID.header : []).concat(FIELDS).filter(Boolean);
  var problem = isInternal(n) ? '“' + n + '” is used by CardBoard' : /[{}]/.test(n) ? 'Names can\'t use { or }'
              : known.some(function(f) { return f.toLowerCase() === n.toLowerCase(); }) ? 'There\'s already a column called “' + n + '”' : '';
  if (problem) { $('gridAddColMsg').textContent = problem; return; }
  var tab = curTab; _addingCol = true; inp.disabled = true; box.classList.add('busy');
  api('addfield', { tab: tab, name: n })
    .then(function(r) {
      if (curTab !== tab) return;
      if (FIELDS.indexOf(n) < 0) { FIELDS.push(n); ROWS.forEach(function(row) { row[n] = ''; }); renderData(); }
      gridAddedColumn(n); hideAddColumn();
      var c = GRID ? GRID.header.indexOf(n) : -1, first = GRID && GRID.rows.length ? 0 : -1;
      if (c >= 0) {
        var th = $('gridWrap').querySelector('th[data-c="' + c + '"]'); if (th) { th.scrollIntoView({ inline: 'nearest', block: 'nearest' }); th.classList.add('just-added'); }
        if (first >= 0) selectGridCell(first, c);
      }
      toast('✓ Added column ' + (r.column ? r.column + ' ' : '') + '“' + n + '” to the sheet');
    })
    .catch(function(e) { $('gridAddColMsg').textContent = e.message; inp.disabled = false; inp.focus(); })
    .then(function() { _addingCol = false; box.classList.remove('busy'); });
}

// Column heading menu: hide the column here and in the Design list
function closeColumnMenu() { var m = $('colMenu'); if (m) m.remove(); document.querySelectorAll('table.grid th.menu-open').forEach(function(t) { t.classList.remove('menu-open'); }); }
function openColumnMenu(th) {
  var open = th.classList.contains('menu-open'); closeColumnMenu(); if (open) return;
  var name = GRID.header[+th.dataset.c]; if (!name) return;
  var used = layout && layout.els.concat(layout.back.els).some(function(e) { return e.field === name || (e.kind === 'text' && (e.text || '').indexOf('{' + name + '}') >= 0); });
  var m = document.createElement('div'); m.id = 'colMenu'; m.className = 'field-menu col-menu';
  m.innerHTML = '<div class="col-menu-name">' + esc(name) + '</div><button type="button"><span class="fm-ico">⊘</span> Hide field</button>' +
    '<p>Hides the column here and in the Design list. It stays in the sheet' + (used ? ', and still shows on the card.' : '.') + ' “Show all” above brings it back.</p>';
  m.querySelector('button').onclick = function() { closeColumnMenu(); setFieldHidden(name, true); toast('Hid “' + name + '”'); };
  document.body.appendChild(m);
  var r = th.getBoundingClientRect();
  m.style.left = Math.min(r.left, innerWidth - m.offsetWidth - 12) + 'px'; m.style.top = (r.bottom + 4) + 'px';
  th.classList.add('menu-open');
}
document.addEventListener('mousedown', function(ev) { if ($('colMenu') && !ev.target.closest('#colMenu') && !ev.target.closest('th.menu-open')) closeColumnMenu(); });
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeColumnMenu(); });
$('gridWrap').addEventListener('scroll', closeColumnMenu);

// Rows changed here: re-read them for the card designer when leaving the Data tab
function refreshRows() {
  var tab = curTab; _rowsStale = false;
  return api('rows', { tab: tab, force: '1' }).then(function(res) {
    if (curTab !== tab) return;
    ROWS = (res.rows || []).filter(function(r) { return Object.keys(r).some(function(k) { return !isInternal(k) && String(r[k]).trim(); }); });
    FIELDS = [];
    ROWS.forEach(function(r) { Object.keys(r).forEach(function(k) { if (!isInternal(k) && FIELDS.indexOf(k) < 0) FIELDS.push(k); }); });
    rowIdx = Math.min(rowIdx, Math.max(0, ROWS.length - 1));
    renderData(); render();
    if (_activeTab === 'print') renderPrint();
    if (_activeTab === 'export') renderExport();
    autoRepublish(tab);
  }).catch(function(e) { toast('Couldn\'t refresh the rows — ' + e.message, true); });
}
(function() {
  var wrap = $('gridWrap');
  wrap.addEventListener('click', function(ev) {
    var th = ev.target.closest('th[data-c]'); if (th) openColumnMenu(th);
  });
  wrap.addEventListener('mousedown', function(ev) {
    var td = ev.target.closest('td[data-c]'); if (!td || ev.target.classList.contains('g-editor')) return;
    selectGridCell(+td.dataset.r, +td.dataset.c);
  });
  wrap.addEventListener('dblclick', function(ev) { if (ev.target.closest('td[data-c]')) startGridEdit(); });
  document.addEventListener('keydown', function(ev) {
    if (_activeTab !== 'data' || _gridEdit || !_gridSel || !GRID) return;
    if (/^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '')) return;
    var k = ev.key, mod = ev.metaKey || ev.ctrlKey;
    if (k === 'ArrowUp') { ev.preventDefault(); moveGridSel(-1, 0); }
    else if (k === 'ArrowDown') { ev.preventDefault(); moveGridSel(1, 0); }
    else if (k === 'ArrowLeft') { ev.preventDefault(); moveGridSel(0, -1); }
    else if (k === 'ArrowRight' || k === 'Tab') { ev.preventDefault(); moveGridSel(0, ev.shiftKey && k === 'Tab' ? -1 : 1); }
    else if (k === 'Enter' || k === 'F2') { ev.preventDefault(); startGridEdit(); }
    else if ((k === 'Delete' || k === 'Backspace') && !mod) {
      ev.preventDefault(); var row = GRID.rows[_gridSel.r], old = row.values[_gridSel.c] || '';
      if (old) saveGridCell(_gridSel.r, _gridSel.c, '', old);
    }
    else if (mod && k.toLowerCase() === 'c') { var v = GRID.rows[_gridSel.r].values[_gridSel.c] || ''; if (navigator.clipboard) navigator.clipboard.writeText(v); }
    else if (k.length === 1 && !mod && !ev.altKey) { ev.preventDefault(); startGridEdit(k); }   // typing replaces, like a spreadsheet
  });
  document.addEventListener('paste', function(ev) {
    if (_activeTab !== 'data' || _gridEdit || !_gridSel || !GRID) return;
    if (/^(INPUT|TEXTAREA)$/.test((document.activeElement || {}).tagName || '')) return;
    var t = (ev.clipboardData && ev.clipboardData.getData('text/plain')) || '';
    if (!t) return; ev.preventDefault();
    var row = GRID.rows[_gridSel.r]; saveGridCell(_gridSel.r, _gridSel.c, t.replace(/\r?\n$/, ''), row.values[_gridSel.c] || '');
  });
})();

// ── Tags (Pro): [name] in any text becomes a word or a picture ───────────────
// ({Column} stays for sheet columns.) Kept in the spreadsheet's "tags" tab as name/value pairs: Tag = "[energy]", Value =
// text, a picture link, or "icon:bolt:#f2c14e" for an icon from the library below.
var TAGS = [], TAGMAP = {};
var ICONS = {
  bolt: '<path d="M13 2 3 14h7l-1 8 10-12h-7z"/>',
  coin: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6.5" fill="#fff" fill-opacity=".35"/>',
  heart: '<path d="M12 21s-7.5-4.6-9.5-9.3C1.1 8.3 3.3 4.5 7 4.5c2 0 3.5 1.1 5 3 1.5-1.9 3-3 5-3 3.7 0 5.9 3.8 4.5 7.2C19.5 16.4 12 21 12 21z"/>',
  star: '<path d="M12 2.5l2.9 6.6 7.1.6-5.4 4.7 1.7 7-6.3-3.8-6.3 3.8 1.7-7L2 9.7l7.1-.6z"/>',
  sparkle: '<path d="M12 2l2 7 7 3-7 3-2 7-2-7-7-3 7-3z"/>',
  shield: '<path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5z"/>',
  sword: '<path d="M14.5 3H21v6.5L10.8 19.7l1.4 1.4-1.4 1.4-2.8-2.8-2.5 2.5-1.4-1.4 2.5-2.5-2.8-2.8 1.4-1.4 1.4 1.4z"/>',
  gem: '<path d="M6 3h12l4 6-10 12L2 9z"/>',
  crown: '<path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5z"/>',
  trophy: '<path fill-rule="evenodd" d="M7 3h10v2h4v3a4 4 0 0 1-4 4h-.4A5 5 0 0 1 13 15v3h3v3H8v-3h3v-3a5 5 0 0 1-3.6-3H7a4 4 0 0 1-4-4V5h4zM5 7v1a2 2 0 0 0 2 2V7zm14 0h-2v3a2 2 0 0 0 2-2z"/>',
  drop: '<path d="M12 2s7 7.6 7 12.5A7 7 0 0 1 5 14.5C5 9.6 12 2 12 2z"/>',
  flame: '<path d="M12 2c1 4 5 5.5 5 11a5 5 0 0 1-10 0c0-2.5 1.2-4 2.5-5 .2 1.8 1 3 2.5 3.5C11 8.5 11 5 12 2z"/>',
  leaf: '<path d="M20 4C9 4 4 9 4 16c0 1.4.2 2.7.5 4 1-3.5 4-7.5 9.5-9-4 2.5-6.5 6-7.5 9.5C15 20 20 14 20 4z"/>',
  sun: '<circle cx="12" cy="12" r="4.5"/><path d="M11 1h2v4h-2zM11 19h2v4h-2zM1 11h4v2H1zM19 11h4v2h-4zM4.2 5.6l1.4-1.4 2.8 2.8-1.4 1.4zM15.6 17l1.4-1.4 2.8 2.8-1.4 1.4zM4.2 18.4l2.8-2.8 1.4 1.4-2.8 2.8zM15.6 7l2.8-2.8 1.4 1.4-2.8 2.8z"/>',
  moon: '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5z"/>',
  cube: '<path fill-rule="evenodd" d="M12 2 3 7v10l9 5 9-5V7zm0 2.3 6.5 3.6L12 11.5 5.5 7.9z"/>',
  card: '<path fill-rule="evenodd" d="M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm1 3v14h10V5z"/>',
  die: '<path fill-rule="evenodd" d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm3 3.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-4 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-4 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>',
  hourglass: '<path d="M6 2h12v2h-1v3l-4 5 4 5v3h1v2H6v-2h1v-3l4-5-4-5V4H6z"/>',
  clock: '<path fill-rule="evenodd" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 4.5h-2V13l5 3 1-1.7-4-2.4z"/>',
  skull: '<path fill-rule="evenodd" d="M12 2C6.5 2 3 5.8 3 10.5c0 3 1.5 5 3.5 6V20h3v-2h1v2h3v-2h1v2h3v-3.5c2-1 3.5-3 3.5-6C21 5.8 17.5 2 12 2zM8.5 9a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm7 0a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/>',
  person: '<circle cx="12" cy="7" r="4"/><path d="M4 21c0-4.4 3.6-7.5 8-7.5s8 3.1 8 7.5z"/>',
  eye: '<path fill-rule="evenodd" d="M12 5C6 5 2 12 2 12s4 7 10 7 10-7 10-7-4-7-10-7zm0 3.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z"/>',
  key: '<circle cx="7.5" cy="12" r="4.5"/><path d="M11 10.5h11v3h-2v3h-3v-3h-6z"/>',
  lock: '<path fill-rule="evenodd" d="M7 10V7a5 5 0 0 1 10 0v3h1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V11a1 1 0 0 1 1-1zm2.5 0h5V7a2.5 2.5 0 0 0-5 0z"/>',
  flag: '<path d="M5 2h2v20H5zM8 3h11l-3 4.5L19 12H8z"/>',
  house: '<path d="M12 3 2 12h3v9h5v-6h4v6h5v-9h3z"/>',
  arrow: '<path d="M4 10.5h11l-4-4L13 4.5 20.5 12 13 19.5l-2-2 4-4H4z"/>',
  plus: '<path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7z"/>',
  minus: '<path d="M3 10h18v4H3z"/>',
  check: '<path d="M9 15.6 4.9 11.5 2.8 13.6 9 19.8 21.2 7.6l-2.1-2.1z"/>',
  cross: '<path d="M6.3 4.2 12 9.9l5.7-5.7 2.1 2.1-5.7 5.7 5.7 5.7-2.1 2.1-5.7-5.7-5.7 5.7-2.1-2.1 5.7-5.7-5.7-5.7z"/>'
};
function iconSvg(name, color) {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="' + (color || '#2e2763') + '">' + (ICONS[name] || ICONS.star) + '</svg>';
}
function iconUri(name, color) { return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(iconSvg(name, color)); }
function parseIcon(v) { var m = /^icon:([a-z]+)(?::(#[0-9a-f]{3,8}))?$/i.exec(String(v || '').trim()); return m ? { name: m[1].toLowerCase(), color: m[2] || '#2e2763' } : null; }
function tagKey(n) { return String(n || '').trim().replace(/^[{\[]+|[}\]]+$/g, '').trim().toLowerCase(); }
function braced(n) { n = String(n || '').trim().replace(/^[{\[]+|[}\]]+$/g, '').trim(); return n ? '[' + n + ']' : ''; }   // tag names are written [energy]
function tagPicture(v) {   // the picture address for an image tag, or '' for a text tag
  var ic = parseIcon(v); if (ic) return iconUri(ic.name, ic.color);
  v = String(v || '').trim();
  return /^(https?:)?\/\//i.test(v) || /^=IMAGE\(/i.test(v) || /^data:image\//.test(v) ? imageUrl(v) : '';
}
function setTags(list) {
  TAGS = list; TAGMAP = {};
  TAGS.forEach(function(t) { t.tag = braced(t.tag); });   // older sheets saved {energy}
  TAGS.forEach(function(t) { TAGMAP[tagKey(t.tag)] = t; });
}
function tagsOn() { return TAGS.length > 0; }
// One block of text, so an inline tag picture sits in the line instead of becoming its own flex item
function rich(t, ctx) { return '<span class="tx">' + expandTags(esc(t), ctx || {}) + '</span>'; }
function expandTags(html, ctx) {
  if (!tagsOn()) return html;
  return html.replace(/\[([^\[\]{}<>]{1,60})\]/g, function(all, name) {
    var t = TAGMAP[tagKey(name)]; if (!t) return all;
    var pic = tagPicture(t.value);
    if (!pic) return esc(t.value);
    if (ctx.proxy) pic = proxied(pic);
    return '<img class="tag-img" src="' + esc(pic) + '" alt="' + esc(name) + '" draggable="false" referrerpolicy="no-referrer">';
  });
}
function refreshCards() { render(); if (_activeTab === 'print') renderPrint(); if (_activeTab === 'export') renderExport(); }
function loadTags() {
  return api('tags').then(function(r) { setTags(r.tags || []); refreshCards(); if (_activeTab === 'tags') renderTags(); })
    .catch(function(e) { if (_activeTab === 'tags') $('tagList').innerHTML = '<div class="muted" style="padding:1rem">Couldn\'t read the tags — ' + esc(e.message) + '</div>'; });
}

// The Tags view
var _tagDraft = null;   // a new tag not saved yet
function tagPreview(t) {
  var pic = tagPicture(t.value);
  return pic ? '<img src="' + esc(pic) + '" alt="" referrerpolicy="no-referrer">' : '<span>' + (t.value ? esc(t.value) : '<i>empty</i>') + '</span>';
}
function renderTags() {
  var box = $('tagList'); if (!box) return;
  var pro = hasAccess();   // read-only once the free week is over
  $('tagsInfo').textContent = TAGS.length + (TAGS.length === 1 ? ' tag' : ' tags');
  var list = (_tagDraft ? [_tagDraft] : []).concat(TAGS);
  if (!list.length) { box.innerHTML = '<div class="tag-empty">No tags yet. <b>+ Add tag</b>, name it (like <code>energy</code>), and give it a word or a picture. Then write <code>[energy]</code> in any text or data on your cards.</div>'; return; }
  box.innerHTML = list.map(function(t, i) {
    var draft = t === _tagDraft, img = draft ? t.kind === 'image' : (t.kind || (tagPicture(t.value) ? 'image' : 'text')) === 'image';
    var idx = draft ? -1 : TAGS.indexOf(t);
    var val = img
      ? '<div class="tag-img-box">' + (tagPicture(t.value) ? '<img src="' + esc(tagPicture(t.value)) + '" alt="" referrerpolicy="no-referrer">' : '<span class="muted">No picture</span>') +
        '<button type="button" class="tag-pick" onclick="pickTagImage(' + idx + ')" title="Choose a picture"' + (pro ? '' : ' disabled') + '>' + IMG_ICON + ' Choose</button>' +
        '<input class="inp" value="' + esc(parseIcon(t.value) ? '' : t.value) + '" placeholder="or paste a picture link" onchange="setTagValue(' + idx + ',this.value.trim())"' + (pro ? '' : ' disabled') + '></div>'
      : '<input class="inp" value="' + esc(t.value) + '" placeholder="What [tag] becomes" onchange="setTagValue(' + idx + ',this.value)"' + (pro ? '' : ' disabled') + '>';
    return '<div class="tag-row' + (draft ? ' draft' : '') + '">' +
      '<input class="inp tag-name" value="' + esc(draft ? t.tag : t.tag) + '" placeholder="[name]" onchange="renameTag(' + idx + ',this.value,this)"' + (draft ? ' id="tagDraftName"' : '') + (pro ? '' : ' disabled') + '>' +
      '<div class="seg tag-kind"><button type="button" class="' + (img ? '' : 'on') + '" onclick="setTagKind(' + idx + ',\'text\')"' + (pro ? '' : ' disabled') + '>Text</button><button type="button" class="' + (img ? 'on' : '') + '" onclick="setTagKind(' + idx + ',\'image\')"' + (pro ? '' : ' disabled') + '>Image</button></div>' +
      '<div class="tag-val">' + val + '</div>' +
      '<div class="tag-prev" title="How it looks on a card">Gain 1 ' + (draft && !t.value ? '<span class="muted">' + esc(t.tag || '[…]') + '</span>' : tagPreview(t)) + '</div>' +
      '<button type="button" class="tag-del" onclick="deleteTag(' + idx + ')" title="Delete this tag"' + (pro ? '' : ' disabled') + '>✕</button></div>';
  }).join('');
}
function addTag() {
  if (!requirePro('tags', addTag)) return;
  if (_tagDraft) { var d = $('tagDraftName'); if (d) d.focus(); return; }
  _tagDraft = { tag: '', value: '', kind: 'text' }; _lastDraft = null; renderTags();
  var n = $('tagDraftName'); if (n) n.focus();
}
var _lastDraft = null;   // a new tag that was just saved: clicks on its old row still find it
// Redraw the list once nothing is being clicked or typed in, so a save finishing mid-click can't swallow the click
var _tagPtr = false, _tagRedraw = false;
document.addEventListener('pointerdown', function() { _tagPtr = true; }, true);
document.addEventListener('pointerup', function() { _tagPtr = false; if (_tagRedraw) setTimeout(laterRenderTags, 0); }, true);
function laterRenderTags() {
  var a = document.activeElement, typing = a && $('tagList') && $('tagList').contains(a) && /^(INPUT|TEXTAREA)$/.test(a.tagName);
  if (_tagPtr || typing) { _tagRedraw = true; if (typing) a.addEventListener('blur', function() { setTimeout(laterRenderTags, 0); }, { once: true }); return; }
  _tagRedraw = false; renderTags();
}
function tagAt(i) { return i < 0 ? (_tagDraft || _lastDraft) : TAGS[i]; }
var _tagSaves = Promise.resolve();   // one save at a time, so a new tag's name and value can't race
function saveTag(t, old) {
  if (!t.tag) return Promise.resolve();
  var p = _tagSaves = _tagSaves.catch(function() {}).then(function() {
    return api('settag', Object.assign({ tag: t.tag, value: t.value || '' }, old && tagKey(old) !== tagKey(t.tag) ? { old: old } : {}));
  });
  return p.then(function() {
    if (t === _tagDraft) { _tagDraft = null; _lastDraft = t; TAGS.push(t); }
    setTags(TAGS); refreshCards();
    laterRenderTags();
    toast('✓ Saved ' + t.tag);
  }).catch(function(e) {
    toast('Couldn\'t save the tag — ' + e.message, true);
    loadTags();
  });
}
function renameTag(i, v, input) {
  var t = tagAt(i), name = braced(v); if (!t) return;
  if (!name) { if (t === _tagDraft) { _tagDraft = null; renderTags(); } else renderTags(); return; }
  if (TAGS.some(function(x) { return x !== t && tagKey(x.tag) === tagKey(name); })) { toast('There\'s already a tag called ' + name, true); renderTags(); return; }
  var old = t === _tagDraft ? '' : t.tag;
  // Show the braces now, but don't redraw the list yet: the click that moved focus away (Image, Choose…) must still land
  t.tag = name; if (input) input.value = name; saveTag(t, old);
}
function setTagValue(i, v) {
  var t = tagAt(i); if (!t) return;
  t.value = v; if (t === _tagDraft && !t.tag) { renderTags(); return; }
  saveTag(t, '');
}
function setTagKind(i, kind) {
  var t = tagAt(i); if (!t) return;
  var isImg = !!tagPicture(t.value);
  t.kind = kind;
  if ((kind === 'image') !== isImg && t.value) { t.value = ''; if (t.tag && t !== _tagDraft) saveTag(t, ''); }
  renderTags();
  if (kind === 'image' && !t.value) pickTagImage(i);
}
function deleteTag(i) {
  var t = tagAt(i); if (!t) return;
  if (t === _tagDraft) { _tagDraft = null; renderTags(); return; }
  askDialog({ title: 'Delete ' + t.tag + '?', message: 'Cards will show ' + t.tag + ' as plain text again. The row is removed from the tags tab.', ok: 'Delete', cancel: 'Keep', danger: true })
    .then(function(yes) {
      if (!yes) return;
      api('deltag', { tag: t.tag }).then(function() { TAGS.splice(TAGS.indexOf(t), 1); setTags(TAGS); refreshCards(); renderTags(); toast('Deleted ' + t.tag); })
        .catch(function(e) { toast('Couldn\'t delete — ' + e.message, true); });
    });
}

// Choosing a tag's picture: an icon from the library, an upload, or a link
function uploadPicture(file) {
  if (!isImageFile(file)) return Promise.reject(new Error('Use a PNG, JPG, GIF or WebP picture'));
  if (file.size > 10 * 1024 * 1024) return Promise.reject(new Error('That picture is over 10 MB'));
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('app', 'cardboard'); fd.append('file', file);
  return cbPost(APP_BASE + 'push/uploadImage.php', fd)
    .then(function(r) { if (!r || r.error || !r.url) throw new Error(r && r.error ? r.error : 'Upload failed'); return r.url; });
}
function pickTagImage(i) {
  var t = tagAt(i); if (!t) return;
  var cur = parseIcon(t.value) || { name: '', color: '#2e2763' }, color = cur.color, tab = parseIcon(t.value) || !t.value ? 'icons' : 'link';
  var ov = document.createElement('div'); ov.className = 'overlay open ask-overlay';
  function body() {
    var h = '<div class="dlg tag-dlg"><h2 class="dlg-title">Picture for ' + esc(t.tag || 'this tag') + '</h2>' +
      '<div class="seg tag-tabs">' + [['icons', 'Icons'], ['upload', 'Upload'], ['link', 'Link']].map(function(x) { return '<button type="button" data-tab="' + x[0] + '" class="' + (tab === x[0] ? 'on' : '') + '">' + x[1] + '</button>'; }).join('') + '</div>';
    if (tab === 'icons') {
      h += '<div class="icon-color"><span class="mini">Color</span><input type="color" value="' + esc(color.length === 7 ? color : '#2e2763') + '" data-color></div><div class="icon-grid">' +
        Object.keys(ICONS).map(function(n) { return '<button type="button" class="icon-cell' + (cur.name === n ? ' on' : '') + '" data-icon="' + n + '" title="' + n + '"><img src="' + iconUri(n, color) + '" alt="' + n + '"></button>'; }).join('') + '</div>';
    } else if (tab === 'upload') {
      h += '<label class="ip-drop tag-drop" data-drop>' + IMG_ICON + '<b>Drop a picture here</b><span>or click to choose a file</span>' +
           '<input type="file" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none" data-file></label><p class="dlg-sub">Saved on zapsheets.com.</p><div class="dlg-err" data-err></div>';
    } else {
      h += '<span class="mini" style="margin-top:.8rem">Picture link</span><input class="inp" data-url value="' + esc(parseIcon(t.value) ? '' : t.value) + '" placeholder="Dropbox, Google Drive or web link">' +
           '<p class="dlg-sub" style="margin-top:.35rem">Shown straight from the source, so changing the picture there changes your cards.</p>';
    }
    h += '<div class="dlg-actions"><button type="button" class="attr-btn" data-close>Cancel</button>' + (tab === 'link' ? '<button type="button" class="print-go" data-use>Use link</button>' : '') + '</div></div>';
    ov.innerHTML = h;
    var file = ov.querySelector('[data-file]'), drop = ov.querySelector('[data-drop]');
    if (file) file.onchange = function() { if (file.files[0]) upload(file.files[0]); };
    if (drop) {
      drop.addEventListener('dragover', function(e) { e.preventDefault(); drop.classList.add('over'); });
      drop.addEventListener('dragleave', function() { drop.classList.remove('over'); });
      drop.addEventListener('drop', function(e) { e.preventDefault(); drop.classList.remove('over'); var f = droppedFile(e.dataTransfer); if (f) upload(f); });
    }
    var cin = ov.querySelector('[data-color]');
    if (cin) cin.oninput = function() { color = cin.value; ov.querySelectorAll('.icon-cell img').forEach(function(im) { im.src = iconUri(im.alt, color); }); };
    var u = ov.querySelector('[data-url]'); if (u) setTimeout(function() { u.focus(); }, 30);
  }
  function done(v) { ov.remove(); document.removeEventListener('keydown', key, true); if (v != null) setTagValue(i, v); }
  function upload(f) {
    if (!hasAccess()) { done(null); requirePro('upload', function() {}); return; }
    var err = ov.querySelector('[data-err]'); err.textContent = 'Uploading…';
    uploadPicture(f).then(function(url) { done(url); }).catch(function(e) { err.textContent = e.message; });
  }
  function key(e) { if (e.key === 'Escape') { e.stopPropagation(); done(null); } if (e.key === 'Enter' && tab === 'link') { e.preventDefault(); done(ov.querySelector('[data-url]').value.trim()); } }
  ov.addEventListener('click', function(e) {
    if (e.target === ov || e.target.closest('[data-close]')) { done(null); return; }
    var tb = e.target.closest('[data-tab]'); if (tb) { tab = tb.dataset.tab; body(); return; }
    var ic = e.target.closest('[data-icon]'); if (ic) { done('icon:' + ic.dataset.icon + ':' + color); return; }
    if (e.target.closest('[data-use]')) done(ov.querySelector('[data-url]').value.trim());
  });
  document.addEventListener('keydown', key, true);
  body(); document.body.appendChild(ov);
}

// ── Dialogs (instead of the browser's confirm/alert) ─────────────────────────
// askDialog({title, message, ok, cancel, danger}) → Promise<boolean>. cancel: '' shows only the OK button.
function askDialog(o) {
  return new Promise(function(done) {
    var ov = document.createElement('div');
    ov.className = 'overlay open ask-overlay';
    ov.innerHTML = '<div class="dlg ask" role="alertdialog" aria-modal="true" aria-labelledby="askTitle">' +
      '<h2 class="dlg-title" id="askTitle">' + esc(o.title || '') + '</h2>' +
      '<p class="ask-msg">' + esc(o.message || '') + '</p>' +
      '<div class="dlg-actions">' + (o.cancel === '' ? '' : '<button type="button" class="attr-btn" data-a="0">' + esc(o.cancel || 'Cancel') + '</button>') +
      '<button type="button" class="print-go' + (o.danger ? ' danger' : '') + '" data-a="1">' + esc(o.ok || 'OK') + '</button></div></div>';
    var prev = document.activeElement;
    function close(v) { document.removeEventListener('keydown', key, true); ov.remove(); if (prev && prev.focus) prev.focus(); done(v); }
    function key(ev) {
      if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); close(false); }
      else if (ev.key === 'Enter') { ev.preventDefault(); ev.stopPropagation(); var f = document.activeElement; close(f && f.dataset && f.dataset.a ? f.dataset.a === '1' : !o.danger); }
      else if (ev.key === 'Tab') {   // keep focus inside the dialog
        var b = ov.querySelectorAll('button'), i = Array.prototype.indexOf.call(b, document.activeElement);
        ev.preventDefault(); b[(i + (ev.shiftKey ? b.length - 1 : 1)) % b.length].focus();
      }
    }
    ov.addEventListener('click', function(ev) {
      var b = ev.target.closest('[data-a]');
      if (b) close(b.dataset.a === '1'); else if (ev.target === ov) close(false);
    });
    document.addEventListener('keydown', key, true);
    document.body.appendChild(ov);
    ov.querySelector(o.danger ? '[data-a="0"]' : '[data-a="1"]').focus();   // risky actions default to Cancel
  });
}

// ── Menu: Profile, Settings, Fetch, Subscribe, Help ──────────────────────────
// Profile and settings live in the spreadsheet's "layouts" tab, on their own row.
var SETTINGS_KEY = '_cardboard_settings', SETTINGS = { units: 'in', profile: {} }, SHEET_TITLE = '';
function openDialog(id) { $(id).classList.add('open'); }
function closeDialog(id) { $(id).classList.remove('open'); }
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(function(o) { o.classList.remove('open'); }); });
function saveSettings() { return api('save', { tab: SETTINGS_KEY, layout: JSON.stringify(SETTINGS) }).then(function() { LAYOUTS[SETTINGS_KEY] = JSON.parse(JSON.stringify(SETTINGS)); }); }

// Profile: kept with the person's CardBoard account (their row in the Admin sheet). The email is
// the one they sign in with; changing it means signing in with the other address.
function openProfileDialog() {
  if (!ACCT) { askSignIn().then(openProfileDialog, function() {}); return; }
  var a = acctInfo(), old = SETTINGS.profile || {};   // older versions kept the profile in the layouts tab
  $('pfName').value = a.name || old.name || ''; $('pfEmail').value = a.email || ''; $('pfStudio').value = a.studio || old.studio || '';
  $('pfErr').textContent = ''; $('pfSave').disabled = false; $('pfSave').textContent = 'Save';
  openDialog('profileOverlay'); setTimeout(function() { $('pfName').focus(); }, 60);
}
function saveProfile() {
  var btn = $('pfSave'); btn.disabled = true; btn.textContent = 'Saving…';
  accountPost('profile', { name: $('pfName').value.trim(), studio: $('pfStudio').value.trim() })
    .then(function(r) { storeAcct(r); closeDialog('profileOverlay'); toast('✓ Profile saved'); })
    .catch(function(e) { $('pfErr').textContent = 'Couldn\'t save — ' + e.message; btn.disabled = false; btn.textContent = 'Save'; });
}
function changeSignInEmail() {
  askDialog({ title: 'Use a different email?', message: 'You’ll be signed out, then you can sign in with the other address. Each email has its own free week and subscription.', ok: 'Continue', cancel: 'Cancel' })
    .then(function(yes) { if (!yes) return; closeDialog('profileOverlay'); forgetAcct(); askSignIn().then(openProfileDialog, function() {}); });
}

function openSettingsDialog() {
  $('setSheetTitle').textContent = SHEET_TITLE || 'This spreadsheet';
  markUnits(); $('setErr').textContent = '';
  $('setPlan').innerHTML = planLabel();
  openDialog('settingsOverlay');
}
function markUnits() { document.querySelectorAll('#setUnits button').forEach(function(b) { b.classList.toggle('on', b.dataset.u === (SETTINGS.units || 'in')); }); }
function setUnitsChoice(u) {
  if (SETTINGS.units === u) return;
  var keep = SETTINGS.units; SETTINGS.units = u; markUnits(); renderAttrs();
  saveSettings().catch(function(e) { SETTINGS.units = keep; markUnits(); renderAttrs(); $('setErr').textContent = 'Couldn\'t save — ' + e.message; });
}
function unitName() { return SETTINGS.units === 'mm' ? 'mm' : 'in'; }
function unitStep() { return SETTINGS.units === 'mm' ? '0.5' : '0.05'; }
function toUnit(pt) { return SETTINGS.units === 'mm' ? Math.round(pt / 72 * 25.4 * 10) / 10 : Math.round(pt / 72 * 100) / 100; }
function fromUnit(v) { v = parseFloat(v); return SETTINGS.units === 'mm' ? v / 25.4 * 72 : v * 72; }

// ── Account: a free week with every feature, then $4.99/month ────────────────
// People sign in with a code sent to their email. The account (and its signed token) is kept
// in this browser, so the app knows the plan straight away; it's checked with the server
// (the CardBoard Admin sheet) every few hours, and right after subscribing.
var ACCT = null, ACCT_KEY = 'cb_account', ACCT_MAX_AGE = 4 * 3600 * 1000;
try { ACCT = JSON.parse(localStorage.getItem(ACCT_KEY) || 'null'); } catch (e) { ACCT = null; }
function acctInfo() { return (ACCT && ACCT.account) || null; }
function storeAcct(res) {
  ACCT = { token: res.token, account: res.account, sheet: SHEET_ID, checked: Date.now() };
  try { localStorage.setItem(ACCT_KEY, JSON.stringify(ACCT)); } catch (e) {}
  applyAcct();
}
function forgetAcct() { ACCT = null; try { localStorage.removeItem(ACCT_KEY); } catch (e) {} applyAcct(); }
function hasAccess() {
  var a = acctInfo(); if (!a) return false;
  if (a.sheet_unlocked && ACCT.sheet === SHEET_ID) return true;
  return a.state !== 'locked' && new Date(a.until).getTime() > Date.now();
}
function daysLeft() { var a = acctInfo(); return a ? Math.max(0, Math.ceil((new Date(a.until).getTime() - Date.now()) / 86400000)) : 0; }
function accountPost(action, extra) {
  var fd = new FormData(); fd.append('action', action); fd.append('sheet', SHEET_ID);
  Object.keys(extra || {}).forEach(function(k) { fd.append(k, extra[k]); });
  if (ACCT && ACCT.token) fd.append('cbt', ACCT.token);
  return fetch(APP_BASE + 'push/cardboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
    .then(function(r) { if (!r || r.error) { var e = new Error(r && r.error ? r.error : 'No response'); e.needAuth = !!(r && r.need_auth); throw e; } return r; });
}
var _acctRefreshing = null;
function acctRefresh(force) {
  if (!ACCT) return Promise.reject(new Error('Not signed in'));
  if (!force && ACCT.sheet === SHEET_ID && Date.now() - (ACCT.checked || 0) < ACCT_MAX_AGE) return Promise.resolve(ACCT);
  if (_acctRefreshing) return _acctRefreshing;
  _acctRefreshing = accountPost('refresh').then(function(r) { storeAcct(r); return ACCT; })
    .catch(function(e) { if (e.needAuth) forgetAcct(); throw e; })
    .then(function(a) { _acctRefreshing = null; return a; }, function(e) { _acctRefreshing = null; throw e; });
  return _acctRefreshing;
}
// The designer follows the account: everything while it has access, read-only once the week is over
function applyAcct() {
  var a = acctInfo(), ok = hasAccess();
  document.body.classList.toggle('cb-locked', !ok);
  var bar = $('lockBar');
  if (bar) {
    bar.hidden = ok;
    if (!a) { $('lockMsg').innerHTML = '<b>Sign in to start designing</b> — your first week is free, with every feature.'; $('lockBtn').textContent = 'Sign in'; $('lockBtn').onclick = function() { askSignIn().catch(function() {}); }; }
    else { $('lockMsg').innerHTML = '<b>Your free week has ended.</b> Your cards and sheets are safe — subscribe for $4.99/month to keep designing.'; $('lockBtn').textContent = 'Subscribe'; $('lockBtn').onclick = function() { openPlansDialog('locked'); }; }
  }
  updateSubMenu();
  if (_activeTab === 'export' && typeof renderLinks === 'function') renderLinks();
  if (_activeTab === 'tags' && typeof renderTags === 'function') renderTags();
  if (typeof renderAttrs === 'function' && layout) renderAttrs();
}
function planLabel() {
  var a = acctInfo();
  if (!a) return '<b>Not signed in</b>';
  if (a.sheet_unlocked && ACCT.sheet === SHEET_ID) return '<b>Unlocked</b> <span class="muted">· activation key</span>';
  if (a.state === 'activated') return '<b>Unlocked</b> <span class="muted">· activation key</span>';
  if (a.state === 'subscribed') return '<b>Subscribed</b>' + (a.period_end ? ' <span class="muted">· renews ' + esc(fmtWhen(a.period_end)) + '</span>' : '');
  if (a.state === 'trial') { var d = daysLeft(); return '<b>Free week</b> <span class="muted">· ' + d + (d === 1 ? ' day' : ' days') + ' left</span>'; }
  return '<b>Free week ended</b>';
}
function fmtWhen(v) {
  var d = /^\d+$/.test(String(v)) ? new Date(+v * (+v < 1e12 ? 1000 : 1)) : new Date(v);
  return isNaN(d) ? String(v) : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
function updateSubMenu() {
  var b = $('cbSubBtn'); if (!b) return;
  var a = acctInfo(), st = a && (a.sheet_unlocked && ACCT.sheet === SHEET_ID ? 'activated' : a.state);
  b.textContent = !a ? 'Sign in' : st === 'subscribed' ? 'Subscribed' : st === 'activated' ? 'Unlocked' : st === 'trial' ? 'Subscribe · ' + daysLeft() + 'd free left' : 'Subscribe';
  b.style.color = st === 'subscribed' || st === 'activated' ? '#4caf50' : '#f5a623';
  var so = $('cbSignOut'); if (so) so.hidden = !a;
}
// Returns true when allowed; otherwise asks to sign in or shows the plans
function requirePro(reason, retry) {
  if (hasAccess()) return true;
  if (!ACCT) askSignIn().then(function() { if (hasAccess()) retry(); }, function() {});
  else openPlansDialog('locked');
  return false;
}
var LOCK_REASONS = { locked: 'Your free week has ended. Subscribe to keep designing — your layouts and sheets are just as you left them.' };
function openPlansDialog(reason) {
  if (!ACCT) { askSignIn().then(function() { openPlansDialog(reason); }, function() {}); return; }
  $('planIntro').textContent = LOCK_REASONS[reason] || 'Every feature, for a week free. Then $4.99 a month to keep designing.';
  openDialog('plansOverlay'); renderPlans();
  acctRefresh(false).then(renderPlans, function() {});
}
function renderPlans() {
  var a = acctInfo() || {}, unlocked = a.state === 'activated' || (a.sheet_unlocked && ACCT && ACCT.sheet === SHEET_ID), sub = a.state === 'subscribed';
  $('planFree').classList.toggle('current', a.state === 'trial' && !unlocked);
  $('planPro').classList.toggle('current', sub || unlocked);
  $('planCurrent').innerHTML = planLabel();
  $('planCurrent').className = 'plan-current' + (sub || unlocked ? ' paid' : '');
  var btn = $('planProBtn'); btn.style.display = sub || unlocked ? 'none' : ''; btn.disabled = false; btn.textContent = 'Subscribe';
  $('planManage').style.display = sub ? '' : 'none';
  $('planManage').textContent = a.status === 'past_due' ? 'Update Payment' : 'Manage Billing';
  $('planStatus').innerHTML = a.status === 'past_due' ? '<span style="color:#d97706;font-weight:700">⚠ Payment past due</span>' : '';
  $('planWho').innerHTML = a.email ? 'Signed in as <b>' + esc(a.email) + '</b>' : '';
  if (window.zsActivationKey) zsActivationKey({ el: $('planActKey'), base: APP_BASE, sheetId: SHEET_ID, activated: !!unlocked,
    onActivated: function() { acctRefresh(true).then(function() { renderPlans(); refreshCards(); }, function() {}); } });
}
function openBillingPortal() {
  var url = acctInfo() && acctInfo().portal_url;
  if (url) window.open(url, '_blank', 'noopener');
  else askDialog({ title: 'Billing', message: 'Online billing management isn’t available yet. Please contact support to change your subscription.', ok: 'OK', cancel: '' });
}
function returnUrl(flag) {
  var u = new URL(window.location.href); u.searchParams.delete('sub'); u.searchParams.delete('donate');
  if (flag) u.searchParams.set(flag[0], flag[1]);
  return u.toString();
}
function startProCheckout() {
  var btn = $('planProBtn'); btn.disabled = true; btn.textContent = 'Redirecting…';
  var fd = new FormData();
  fd.append('type', 'subscription'); fd.append('id', SHEET_ID); fd.append('app', 'cardboard'); fd.append('plan', 'pro');
  if (ACCT && ACCT.token) fd.append('cbt', ACCT.token);
  fd.append('success_url', returnUrl(['sub', 'success'])); fd.append('cancel_url', returnUrl(null));
  fetch(APP_BASE + 'push/stripeCheckout.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
    .then(function(r) { if (!r || r.error || !r.url) throw new Error(r && r.error ? r.error : 'Could not start checkout'); window.location.href = r.url; })
    .catch(function(e) { btn.disabled = false; btn.textContent = 'Subscribe'; $('planStatus').textContent = e.message; });
}
function signOut() {
  askDialog({ title: 'Sign out?', message: 'You can sign back in any time with a code sent to your email.', ok: 'Sign out', cancel: 'Cancel' })
    .then(function(yes) { if (yes) { forgetAcct(); window.location.href = APP_BASE + 'cardboard'; } });
}

// Sign in. Resolves once signed in; rejects if closed.
//   login  — email + password
//   signup — email + password, then a code from the email confirms it and starts the free week
//   reset  — email + new password, then a code from the email
var _signIn = null, _siMode = '', _siPending = null;
// gate: first visit — the page stays empty behind it, and it can't be closed (only left for the start page)
function askSignIn(gate) {
  if (_signIn) { openDialog('signinOverlay'); return _signIn.promise; }
  var d = {}; d.promise = new Promise(function(res, rej) { d.res = res; d.rej = rej; }); _signIn = d;
  document.body.classList.toggle('cb-gate', !!gate);
  $('siClose').textContent = gate ? 'Back to start' : 'Not now';
  $('siEmail').value = (acctInfo() && acctInfo().email) || (SETTINGS && SETTINGS.profile && SETTINGS.profile.email) || '';
  signInStep('');
  openDialog('signinOverlay');
  return d.promise;
}
function closeSignIn() {
  if (document.body.classList.contains('cb-gate')) { window.location.href = APP_BASE + 'cardboard'; return; }
  closeDialog('signinOverlay'); if (_signIn) { var d = _signIn; _signIn = null; d.rej(new Error('Not signed in')); }
}
function signInChoose(mode) { signInStep(mode === 'trial' ? 'signup' : mode); }
function signInStep(mode) {   // '' = the two choices
  _siMode = mode; _siPending = null;
  $('siChoose').hidden = !!mode; $('siStep1').hidden = !mode; $('siStep2').hidden = true; $('siErr').textContent = '';
  $('siTitle').textContent = { login: 'Log in', signup: 'Start your free week', reset: 'Reset your password' }[mode] || 'Welcome to CardBoard';
  $('siSub').textContent = { login: 'Use your CardBoard email and password.',
    signup: 'Every feature for 7 days — no card needed. Choose a password; we’ll email you a code to confirm.',
    reset: 'Choose a new password; we’ll email you a code to confirm it’s you.' }[mode] || 'Design and print cards from your Google Sheet.';
  $('siPass').value = ''; $('siPass2').value = '';
  $('siPass').placeholder = mode === 'login' ? 'Password' : 'Password (8 or more characters)';
  $('siPass').autocomplete = mode === 'login' ? 'current-password' : 'new-password';
  $('siPass2').hidden = mode === 'login';
  $('siForgot').hidden = mode !== 'login';
  $('siSend').disabled = false; $('siSend').textContent = { login: 'Log in', signup: 'Create account', reset: 'Email me a code' }[mode] || '';
  $('siSwitch').textContent = mode === 'login' ? 'New here? Start your free week' : mode ? 'Already have an account? Log in' : '';
  if (mode) setTimeout(function() { ($('siEmail').value ? $('siPass') : $('siEmail')).focus(); }, 40);
}
function signInSwitch() { signInStep(_siMode === 'login' ? 'signup' : 'login'); }
function signedIn(r) {
  storeAcct(r); closeDialog('signinOverlay'); document.body.classList.remove('cb-gate');
  var a = r.account; toast(a.state === 'trial' ? '✓ Signed in — ' + daysLeft() + (daysLeft() === 1 ? ' day' : ' days') + ' of your free week left' : '✓ Signed in');
  if (_signIn) { var d = _signIn; _signIn = null; d.res(ACCT); }
}
function signInSubmit() {
  var em = $('siEmail').value.trim(), pw = $('siPass').value;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { $('siErr').textContent = 'Enter your email address.'; return; }
  if (_siMode === 'login') { if (!pw) { $('siErr').textContent = 'Enter your password.'; return; } }
  else {
    if (pw.length < 8) { $('siErr').textContent = 'Use at least 8 characters for your password.'; return; }
    if (pw !== $('siPass2').value) { $('siErr').textContent = 'The two passwords don’t match.'; return; }
  }
  var b = $('siSend'), label = b.textContent; b.disabled = true; b.textContent = _siMode === 'login' ? 'Logging in…' : 'Sending…'; $('siErr').textContent = '';
  var done = function() { b.disabled = false; b.textContent = label; };
  if (_siMode === 'login') {
    accountPost('login', { email: em, password: pw }).then(signedIn)
      .catch(function(e) { $('siErr').textContent = e.message; }).then(done);
    return;
  }
  accountPost('send', { email: em, mode: _siMode }).then(function(r) {
    _siPending = { email: em, password: pw };
    $('siSent').innerHTML = 'We sent a 6-digit code to <b>' + esc(em) + '</b>. Enter it to ' + (_siMode === 'reset' ? 'set your new password.' : 'confirm your email and start your free week.');
    $('siTitle').textContent = 'Check your email'; $('siSub').textContent = '';
    $('siStep1').hidden = true; $('siStep2').hidden = false; $('siSwitch').textContent = ''; $('siCode').value = r.debug_code || '';
    $('siGo').textContent = _siMode === 'reset' ? 'Set password' : 'Start my free week';
    setTimeout(function() { $('siCode').focus(); }, 40);
  }).catch(function(e) { $('siErr').textContent = e.message; }).then(done);
}
function signInVerify() {
  var code = $('siCode').value.replace(/\D/g, '');
  if (!_siPending) { signInStep(_siMode || 'signup'); return; }
  if (code.length !== 6) { $('siErr').textContent = 'Enter the 6-digit code from the email.'; return; }
  var b = $('siGo'), label = b.textContent; b.disabled = true; b.textContent = 'Checking…'; $('siErr').textContent = '';
  var name = (SETTINGS && SETTINGS.profile && SETTINGS.profile.name) || '';
  accountPost('verify', { email: _siPending.email, password: _siPending.password, code: code, name: name }).then(function(r) { _siPending = null; signedIn(r); })
    .catch(function(e) { $('siErr').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = label; });
}
function signInBack() { var em = $('siEmail').value; signInStep(_siMode || 'signup'); $('siEmail').value = em; }

(function() {   // back from Stripe Checkout: check the account again once the webhook has landed
  var u = new URL(window.location.href);
  if (u.searchParams.get('sub') === 'success') {
    u.searchParams.delete('sub'); window.history.replaceState(null, '', u.pathname + u.search + u.hash);
    setTimeout(function() { toast('✓ Thanks for subscribing to CardBoard'); }, 400);
    [2500, 8000].forEach(function(ms) { setTimeout(function() { if (ACCT) acctRefresh(true).then(function() { refreshCards(); }, function() {}); }, ms); });
  }
})();

// ── Tabs, menu, donation ──────────────────────────────────────────────────────
function switchTab(t) {
  _activeTab = t;
  if (_gridEdit) endGridEdit(true);
  if (t !== 'data' && _rowsStale) refreshRows();
  ['data', 'tags', 'design', 'print', 'export'].forEach(function(k) {
    var cap = k.charAt(0).toUpperCase() + k.slice(1);
    $('tab' + cap).classList.toggle('active', t === k); $('view-' + k).classList.toggle('active', t === k);
  });
  if (t === 'print') renderPrint(); else if (t === 'export') renderExport(); else if (t === 'data') loadGrid(); else if (t === 'tags') renderTags(); else fitZoom();
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
