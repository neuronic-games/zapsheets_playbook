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
var _printSettings = {}, _exportSettings = {};   // per-tab print/export settings
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
var KIND_NAME = { text: 'Text', data: 'Card', image: 'Image', shape: 'Shape', gen: 'Gen' };   // "Card": a field from the deck's cards
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
function isTextLike(e) { return e.kind === 'text' || e.kind === 'gen' || (e.kind === 'data' && !dataIsImage(e)); }
// Data shown through a mapping: e.as === 'map', e.map = {items:[{v, t, src}]}.
// The card's value in e.field picks the item whose v matches (ignoring case); each item shows
// either a text (t) or a picture (src), so one element can be text on one card and a picture on
// the next. A value with no match shows as it is (a picture if it's a picture link or [tag]).
function dataIsImage(e) { return e.kind === 'data' && e.as === 'image'; }
function mapPicture(e, row) {   // the picture a mapped element shows on this card, or ''
  if (!row) return '';
  var hit = mapHit(e, row);
  return hit ? (hit.src ? imageUrl(picRef(hit.src)) : '') : imageUrl(picRef(cleanVal(row[e.field]).trim()));
}
function mapHit(e, row) {
  if (!row || !e.map) return null;
  var low = cleanVal(row[e.field]).trim().toLowerCase(); if (!low) return null;
  return (e.map.items || []).filter(function(it) { return String(it.v || '').trim().toLowerCase() === low; })[0] || null;
}
// Each field's mappings are also kept with the layout (lay.maps = {field: {items}}), so they stay when the element
// showing them is deleted or set back to Text: choosing Mapping again brings them back, and the Cards view keeps
// showing "value → result" for that field.
function rememberMaps(lay) {
  lay = lay || layout; if (!lay) return;
  if (!lay.maps) lay.maps = {};
  [lay, lay.back || {}].forEach(function(f) {
    (f.els || []).forEach(function(e) {
      if (e.kind === 'data' && e.as === 'map' && e.map && e.field) lay.maps[e.field] = { items: JSON.parse(JSON.stringify(e.map.items || [])) };
    });
  });
}
function savedMap(field) {   // a copy of the field's remembered mappings, or an empty one
  var m = layout && layout.maps && layout.maps[field];
  return { items: m && m.items ? JSON.parse(JSON.stringify(m.items)) : [] };
}
// The front is the layout itself; the back is layout.back ({bg, els})
function faceOf(lay, s) { return s === 'back' ? lay.back : lay; }
function curEls() { return faceOf(layout, side).els; }
// Does the back change from card to card (column values, generated values)?
function backVaries(lay) {
  return (lay || layout).back.els.some(function(e) { return e.kind === 'data' || e.kind === 'gen' || (e.kind === 'text' && /\{[^{}]+\}/.test(e.text || '')); });
}
function isImageLike(e) { return e.kind === 'image' || dataIsImage(e); }

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
    } else if (e.kind === 'image' && (e.map || e.mapOn)) {
      // The old image mapping (an image picking its picture by a field's value) becomes a Card field shown as a Mapping
      var om = e.map || {};
      if (e.mapOn && om.field) {
        e.kind = 'data'; e.field = om.field; e.as = 'map';
        e.map = { items: (om.items || []).map(function(it) { return { v: it.v || '', src: it.src || '' }; }) };
        delete e.src;
      } else delete e.map;
      delete e.mapOn;
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
  if (!lay.maps || typeof lay.maps !== 'object' || Array.isArray(lay.maps)) lay.maps = {};
  rememberMaps(lay);
  lay.v = 3;
  return lay;
}

// ── Server ────────────────────────────────────────────────────────────────────
function api(action, extra) {
  if (extra && extra.tab && isNewTab(extra.tab)) {   // a deck added here and not saved yet: nothing in the sheet to read
    if (action === 'peek' || action === 'rows') return Promise.resolve({ ok: true, rows: [], full: true });
    if (action === 'grid') return Promise.resolve({ ok: true, header: [], rows: [] });
  }
  extra = extra || {};
  if (CACHE_READS[action] && !extra.force) return cachedRead(action, extra);   // what's kept here first
  return apiNet(action, extra).then(function(res) { cacheAfter(action, extra, res); return res; });
}
function apiNet(action, extra) {
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('action', action);
  Object.keys(extra || {}).forEach(function(k) { fd.append(k, extra[k]); });
  return cbPost(APP_BASE + 'push/cardboardData.php', fd)
    .then(function(res) { if (!res || res.error) throw new Error(res && res.error ? res.error : 'No response'); return res; });
}

// ── Kept in this browser: the sheet's data, so the designer opens and switches decks without
// reading Google Sheets again, and keeps working offline. In IndexedDB (lots of room, apart from the
// unsaved changes in local storage), per spreadsheet:
//   start (title, decks, layouts) · rows:<deck> (the cards) · grid:<deck> (the Cards view) · tags · pub:<deck>
// Read from the sheet only when it isn't here, after Save changes it, or with Fetch (which clears it all).
var CACHE_READS = { start: 1, peek: 1, rows: 1, grid: 1, tags: 1 };
var _idb = null;
function idb() {
  if (_idb) return _idb;
  _idb = new Promise(function(ok, no) {
    try {
      var r = indexedDB.open('cardboard', 1);
      r.onupgradeneeded = function() { r.result.createObjectStore('kv'); };
      r.onsuccess = function() { ok(r.result); }; r.onerror = function() { no(r.error); };
    } catch (e) { no(e); }
  });
  return _idb;
}
function ck(k) { return 'cb:' + SHEET_ID + ':' + k; }
function cacheGet(k) {
  return idb().then(function(db) { return new Promise(function(ok) {
    var q = db.transaction('kv').objectStore('kv').get(k); q.onsuccess = function() { ok(q.result); }; q.onerror = function() { ok(undefined); };
  }); }).catch(function() { return undefined; });
}
function cacheSet(k, v) {
  var copy = JSON.parse(JSON.stringify(v));   // as it is now: what's in memory keeps changing
  return idb().then(function(db) { return new Promise(function(ok) {
    var t = db.transaction('kv', 'readwrite'); t.objectStore('kv').put(copy, k); t.oncomplete = ok; t.onerror = ok; t.onabort = ok;
  }); }).catch(function() {});
}
function cacheDel(k) {
  return idb().then(function(db) { return new Promise(function(ok) {
    var t = db.transaction('kv', 'readwrite'); t.objectStore('kv').delete(k); t.oncomplete = ok; t.onerror = ok; t.onabort = ok;
  }); }).catch(function() {});
}
function cacheClear() {   // everything kept for this spreadsheet
  var lo = ck(''), hi = lo + '\uffff';
  return idb().then(function(db) { return new Promise(function(ok) {
    var t = db.transaction('kv', 'readwrite'); t.objectStore('kv').delete(IDBKeyRange.bound(lo, hi)); t.oncomplete = ok; t.onerror = ok; t.onabort = ok;
  }); }).catch(function() {});
}
function cachedRead(action, x) {
  var net = function() { return apiNet(action, x).then(function(res) { cacheAfter(action, x, res); return res; }); };
  if (action === 'start') return cacheGet(ck('start')).then(function(st) {
    if (!st || !st.tabs) return net();
    var titles = st.tabs.map(function(t) { return t.title; }), lay = st.layouts || {};
    var tab = titles.indexOf(x.tab) >= 0 ? x.tab : (titles.filter(function(t) { return lay[t]; })[0] || titles[0] || '');
    return cacheGet(ck('rows:' + tab)).then(function(rows) {
      return { ok: true, title: st.title, tabs: st.tabs, layouts: lay, tab: tab, rows: rows || [], full: !!rows, cached: true };
    });
  });
  var key = action === 'tags' ? 'tags' : (action === 'grid' ? 'grid:' : 'rows:') + x.tab;
  return cacheGet(ck(key)).then(function(v) {
    if (v == null) return action === 'peek' ? apiNet('peek', x) : net();   // the first few rows aren't kept; all of them are
    if (action === 'grid') return { ok: true, header: v.header || [], rows: v.rows || [] };
    if (action === 'tags') return v;
    return { ok: true, rows: v, full: true };
  });
}
function cacheAfter(action, x, res) {   // keep what was read; forget what a change made out of date
  if (!res || res.error) return;
  var tab = x.tab || x.sheet || '';
  if (action === 'start') {
    cacheSet(ck('start'), { title: res.title, tabs: res.tabs || [], layouts: res.layouts || {} });
    if (res.full && res.tab && res.rows) cacheSet(ck('rows:' + res.tab), res.rows);
  }
  else if (action === 'rows') cacheSet(ck('rows:' + tab), res.rows || []);
  else if (action === 'grid') cacheSet(ck('grid:' + tab), { header: res.header || [], rows: res.rows || [] });
  else if (action === 'tags') cacheSet(ck('tags'), res);
  else if (/^(savecells|setcell|addrow|deleterow|deletefield|renamefield|addfield|reorder)$/.test(action)) { cacheDel(ck('rows:' + tab)); cacheDel(ck('grid:' + tab)); }
  else if (action === 'settag' || action === 'deltag') cacheDel(ck('tags'));
}
function writeStartCache() {   // the decks and saved layouts as they are now (after Save)
  if (!SHEET_TITLE && !TABS.length) return;
  cacheSet(ck('start'), { title: SHEET_TITLE, tabs: TABS.filter(function(t) { return !t.pending; }).map(function(t) { return { title: t.title, rows: t.rows, cols: t.cols }; }), layouts: LAYOUTS });
}
function savePub(tab) { cacheSet(ck('pub:' + tab), PUBLISHED[tab] || {}); }
// POST with the account token. A stale sign-in is refreshed once and the request sent again;
// no sign-in asks for one; a finished free week locks the designer.
function cbPost(url, fd, again) {
  if (ACCT && ACCT.token) fd.set('cbt', ACCT.token);
  return fetch(url, { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(res) {
    if (res && res.need_auth && !again && !_guest) {   // a read-only viewer isn't asked to sign in by the page itself
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
function isInternal(k) { return k === '_row' || k === '_notes' || k === '_pend'; }
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
// A value that is a picture: an image file link, =IMAGE(), or a Drive / Dropbox / Google-hosted picture
var IMG_LINK_RE = /\.(png|jpe?g|gif|webp|svg)(\?|$|&)|=IMAGE|drive\.google|googleusercontent|dropbox(usercontent)?\.com/i;
function isImageLink(v) { v = cleanVal(v).trim(); var u = imageUrl(v); return !!u && (IMG_LINK_RE.test(v) || IMG_LINK_RE.test(u)); }   // also a link written [name](url)
function looksLikeImage(field) {
  var n = 0, img = 0;
  ROWS.slice(0, 12).forEach(function(r) {
    var v = cleanVal(r[field]).trim();
    if (v) { n++; if (isImageLink(v)) img++; }
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
      if (e.as === 'map') {
        var mu = mapPicture(e, row), hit = mapHit(e, row);
        if (mu) return imgDiv(mu, e, '', ctx);
        if (!row) return '<span class="el-empty">' + esc(e.field) + '</span>';
        return rich(hit ? (hit.t || '') : textVal(row[e.field]), ctx);
      }
      return row ? rich(textVal(row[e.field]), ctx) : '<span class="el-empty">' + esc(e.field) + '</span>';
    case 'image': { var iu = imageUrl(picRef(e.src)); return iu || ctx && ctx.designing ? imgDiv(iu, e, 'Drop an image here, or double-click to choose', ctx) : ''; }
    case 'shape': return shapeSVG(e);
    case 'gen':   return rich(fill(genValue(e, ctx), row), ctx);   // {Field} in Before / After / the list: that card's value
  }
  return '';
}
function renderCardHTML(lay, row, designing, ctx) {
  ctx = Object.assign({ card: 0, row: 0 }, ctx || {}, { designing: !!designing });
  var face = faceOf(lay, ctx.side), cs = ctx.side === 'back' ? Object.assign({}, lay.card, { bg: lay.back.bg }) : lay.card;
  var h = '<div class="card' + (designing ? ' designing' : '') + (ctx.side === 'back' ? ' back' : '') + '" style="' + cardStyle(cs) + '">';
  var keyIds = designing ? (keyUnit() || []).map(function(x) { return x.id; }) : [];
  function elHTML(e, list) {
    var inner = elInner(e, row, ctx);
    // In a list, a text or picture with nothing to show takes no room at all
    if (list && (inner === '' || (isTextLike(e) && !/<(img|svg)\b/.test(inner) && !inner.replace(/<[^>]*>/g, '').trim()))) return '';
    var cls = 'el k-' + e.kind + (dataIsImage(e) || (e.kind === 'data' && e.as === 'map' && mapPicture(e, row)) ? ' as-image' : '') + (e.valign === 'middle' ? ' v-middle' : e.valign === 'bottom' ? ' v-bottom' : '') + (designing && isSel(e.id) ? ' picked' + (multi.length ? ' multi' : '') : '');
    if (designing && UPLOADING[e.id]) cls += ' img-loading';
    if (keyIds.indexOf(e.id) >= 0) cls += ' key';
    if (e.group && off[e.group]) cls += ' when-off';
    var st = elStyle(e);
    if (list) {   // sized by its contents along the list: as wide (or as tall) as it needs, up to its own box
      cls += ' in-list';
      if (isTextLike(e)) st += list === 'h' ? 'width:auto;max-width:' + e.w + 'pt;' : 'height:auto;max-height:' + e.h + 'pt;';
    }
    return '<div class="' + cls + '" data-id="' + e.id + '" style="' + st + '">' + (designing
      ? '<div class="el-inner">' + inner + '</div>' +
        ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].map(function(d) { return '<span class="hdl ' + d + '" data-h="' + d + '"></span>'; }).join('') +
        '<span class="hdl rot" data-h="rot" title="Drag to rotate · Shift: 15° steps"></span>'
      : inner) + '</div>';
  }
  // Groups set out as a horizontal or vertical list flow their members, in Layers order, from the group's top-left corner
  var lists = {}, selG = designing && multi.length ? selGroupId() : '';
  (face.groups || []).forEach(function(g) { if (g.list === 'h' || g.list === 'v') lists[g.id] = g; });
  // A group with a Mapping shows only on cards whose value is one of its values (faint while designing, so it can still be picked)
  var off = {};
  (face.groups || []).forEach(function(g) { if (!groupShows(g, row)) off[g.id] = 1; });
  var sorted = face.els.filter(function(e) { return designing || !(e.group && off[e.group]); }).sort(function(a, b) { return (a.z || 1) - (b.z || 1); });
  sorted.forEach(function(e) {
    var g = e.group && lists[e.group];
    if (!g) { h += elHTML(e); return; }
    var members = sorted.filter(function(x) { return x.group === g.id; });
    if (members[members.length - 1] !== e) return;   // the list is drawn once, where its top member sits
    var bb = selBox(members), gap = g.gap == null ? 4 : +g.gap || 0, al = { start: 'flex-start', center: 'center', end: 'flex-end' }[listAlign(g)];
    h += '<div class="el-list list-' + g.list + (g.id === selG ? ' picked' : '') + '" data-g="' + g.id + '" style="left:' + bb.x + 'pt;top:' + bb.y + 'pt;z-index:' + (e.z || 1) + ';gap:' + gap + 'pt;align-items:' + al + '">' +
         members.slice().reverse().map(function(x) { return elHTML(x, g.list); }).join('') + '</div>';
  });
  if (designing && multi.length && !(selG && lists[selG])) {   // one box around everything selected (a group or several elements)
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
  if (!ACCT && !_guest) {
    $('cardHolder').innerHTML = '';
    askSignIn(true).then(function() { init(); }, function() {});
    return;
  }
  if (ACCT) acctRefresh(navigator.onLine || !hasAccess())   // who owns the sheet can change: check every time it opens.then(function() {}, function() {});
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
      SETTINGS = Object.assign({ units: 'in' }, LAYOUTS[SETTINGS_KEY] || {}, lsGet('cb_settings:' + SHEET_ID) || {});
      _oldProfile = SETTINGS.profile || null; delete SETTINGS.profile;   // the profile lives with the account now
      SHEET_TITLE = res.title || '';
      rememberSeen();
      if (res.title) { $('subTitle').textContent = res.title; document.title = res.title; setHomeName(res.title); }
      mergeNewTabs(); fillDeckSelects();
      var sel = $('sheetSel');
      var start = isNewTab(want) ? want : (res.tab || want);
      if (!TABS.some(function(t) { return t.title === start; })) {
        start = (TABS.filter(function(t) { return LAYOUTS[t.title]; })[0] || TABS[0] || {}).title || '';
      }
      if (!start) { $('cardHolder').innerHTML = '<div class="muted">This spreadsheet has no decks to design from — each tab of the spreadsheet is a deck.</div>'; return; }
      sel.value = start; $('gridSheet').value = start; $('pSheet').value = start; $('eSheet').value = start;
      refreshSheetCombos(); updateSaveBtn();
      selectTab(start, true, false, false, res.rows && res.tab === start ? res : null);
    })
    .catch(function(e) {
      $('cardHolder').innerHTML = '<div class="muted" style="max-width:360px;text-align:center">Couldn\'t open the sheet — ' + esc(e.message) +
        '<br><br>Make sure it\'s shared with <b>editor@zapsheets-480701.iam.gserviceaccount.com</b>.</div>';
    });
}

// The sheets opened in this browser, newest first (the Designs page lists the ones that aren't yours too)
function rememberSeen() {
  var l = lsGet('cb_seen'); if (!Array.isArray(l)) l = [];
  l = l.filter(function(x) { return x && x.id !== SHEET_ID; }); l.unshift({ id: SHEET_ID, t: Date.now() });
  lsSet('cb_seen', l.slice(0, 60));
}
// ── Sheet picker: a combo box over the hidden <select>s (sheetSel, gridSheet) ────
// Type to filter the tabs; sheets that already have a layout show the layout icon.
var LAYOUT_ICON = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M5 8h14"/><rect x="8" y="11" width="8" height="4.5" rx="1" fill="currentColor" stroke="none"/><path d="M8 18.3h5"/></svg>';
function hasLayout(t) { return !!LAYOUTS[t] && t !== SETTINGS_KEY; }
// Added to the Home Screen under the spreadsheet's name (iOS reads the meta tag, others the manifest)
function setHomeName(t) {
  var m = document.querySelector('meta[name="apple-mobile-web-app-title"]'); if (m && m.content !== t) m.content = t;
  var l = $('cbManifest'); if (l && l.href.indexOf('&name=') < 0) l.href = l.href + '&name=' + encodeURIComponent(t);
}
// ── New decks: added from the Deck box, kept in this browser (cb_newtabs:<sheet>) until Save,
// which adds the tab to the spreadsheet first, then its columns, cards and layout.
function newTabs() { var l = lsGet('cb_newtabs:' + SHEET_ID); return Array.isArray(l) ? l : []; }
function isNewTab(t) { return !!t && newTabs().indexOf(t) >= 0; }
function setNewTabs(l) { lsSet('cb_newtabs:' + SHEET_ID, l.length ? l : null); updateSaveBtn(); }
function mergeNewTabs() {   // the new decks go at the end of the list; drop any the sheet now has
  var have = TABS.map(function(t) { return t.title.toLowerCase(); }), keep = [];
  newTabs().forEach(function(n) { if (have.indexOf(n.toLowerCase()) < 0) { keep.push(n); TABS.push({ title: n, rows: 0, cols: 0, pending: true }); } });
  if (keep.length !== newTabs().length) setNewTabs(keep);
}
function fillDeckSelects() {
  var opts = TABS.map(function(t) { return '<option>' + esc(t.title) + '</option>'; }).join('');
  ['sheetSel', 'gridSheet', 'pSheet', 'eSheet'].forEach(function(id) { var s = $(id); if (s) { var v = s.value; s.innerHTML = opts; if (v) s.value = v; } });
}
function newDeckProblem(n) {
  if (!n) return 'Type a name for the new deck';
  if (n.length > 100) return 'Use up to 100 characters';
  if (/[\/\\]/.test(n)) return 'Names can\'t use / or \\';
  if (/^(layouts|tags)$/i.test(n) || n === SETTINGS_KEY) return '“' + n + '” is used by CardBoard';
  if (TABS.some(function(t) { return t.title.toLowerCase() === n.toLowerCase(); })) return 'There\'s already a deck called “' + n + '”';
  return '';
}
function addNewDeck(n) {
  n = String(n || '').trim().replace(/\s+/g, ' ');
  if (!hasAccess()) { requirePro('edit', function() {}); return; }
  var why = newDeckProblem(n); if (why) { toast(why, true); return; }
  var l = newTabs(); l.push(n); setNewTabs(l);
  TABS.push({ title: n, rows: 0, cols: 0, pending: true });
  fillDeckSelects(); selectTab(n);
  toast('New deck “' + n + '” — add columns and cards, then Save to put it in your sheet');
}
// The current deck's card count, shown in each Deck box (once its rows are in)
var _countTab = '';
function updateDeckCount() {
  var txt = !curTab || _countTab !== curTab || (!ROWS.length && _rowsPartial) ? '' : ROWS.length + (_rowsPartial ? '…' : '');
  document.querySelectorAll('.sheet-combo .sc-count').forEach(function(c) { c.textContent = txt; c.hidden = !txt; c.title = txt ? ROWS.length + (ROWS.length === 1 ? ' card' : ' cards') : ''; });
}
function refreshSheetCombos() {
  ['sheetSel', 'gridSheet', 'pSheet', 'eSheet'].forEach(function(id) {
    var sel = $(id); if (!sel) return;
    var box = sel.nextElementSibling && sel.nextElementSibling.classList.contains('sheet-combo') ? sel.nextElementSibling : null;
    if (!box) { box = makeSheetCombo(sel); }
    var v = sel.value, inp = box.querySelector('input');
    if (document.activeElement !== inp) inp.value = v;
    box.querySelector('.sc-ico').innerHTML = hasLayout(v) ? LAYOUT_ICON : '';
    box.classList.toggle('has-layout', hasLayout(v));
  });
  updateDeckCount();
}
function makeSheetCombo(sel) {
  sel.style.display = 'none';
  var box = document.createElement('div');
  box.className = 'sheet-combo' + (sel.id === 'gridSheet' ? ' compact' : '');
  box.innerHTML = '<span class="sc-ico" title="Has a layout"></span><input class="inp sel-sheet" autocomplete="off" spellcheck="false" aria-label="Deck" role="combobox" aria-expanded="false"><span class="sc-count" hidden></span>' +
    '<button type="button" class="sc-caret" tabindex="-1" aria-label="Show decks"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></button><div class="sc-list" role="listbox"></div>';
  sel.parentNode.insertBefore(box, sel.nextSibling);
  var inp = box.querySelector('input'), list = box.querySelector('.sc-list'), hi = 0, items = [], naming = false, typed = '';
  var NEW = '\u0000new';   // the "New deck" entry at the end of the list
  function draw(filter) {
    if (naming) {   // naming a new deck: just a hint (or what's wrong with the name)
      var n = inp.value.trim().replace(/\s+/g, ' '), why = n ? newDeckProblem(n) : '';
      items = [];
      list.innerHTML = '<div class="sc-none sc-newhint' + (why ? ' bad' : '') + '">' + esc(why || (n ? 'Press Enter to add “' + n + '”' : 'Type the new deck’s name, then press Enter')) + '</div>';
      return;
    }
    var f = (filter || '').trim().toLowerCase(); typed = (filter || '').trim().replace(/\s+/g, ' ');
    items = TABS.map(function(t) { return t.title; }).filter(function(t) { return !f || t.toLowerCase().indexOf(f) >= 0; });
    var exact = f && TABS.some(function(t) { return t.title.toLowerCase() === f; });
    if (!exact) items.push(NEW);
    hi = Math.max(0, items.indexOf(sel.value)); if (f) hi = 0;
    list.innerHTML = items.map(function(t, i) {
      if (t === NEW) return '<div class="sc-item sc-new' + (i === hi ? ' hi' : '') + '" role="option" data-i="' + i + '"><span class="sc-mark">+</span><span class="sc-name">' +
        (typed ? 'New deck “' + esc(typed) + '”' : 'New deck…') + '</span></div>';
      var pend = isNewTab(t);
      return '<div class="sc-item' + (i === hi ? ' hi' : '') + (t === sel.value ? ' cur' : '') + '" role="option" data-i="' + i + '">' +
        '<span class="sc-mark">' + (hasLayout(t) ? LAYOUT_ICON : '') + '</span><span class="sc-name">' + esc(t) + '</span>' +
        (pend ? '<span class="sc-pend" title="Added here — Save to put it in your sheet">unsaved</span>' : '') + (t === sel.value ? '<span class="sc-check">✓</span>' : '') + '</div>';
    }).join('');
    var h = list.querySelector('.hi'); if (h) h.scrollIntoView({ block: 'nearest' });
  }
  function startNaming(name) {
    naming = true; box.classList.add('naming');
    inp.value = name || ''; inp.placeholder = 'New deck name'; inp.focus(); draw();
  }
  function stopNaming() { naming = false; box.classList.remove('naming'); inp.placeholder = ''; }
  function open() { if (box.classList.contains('open')) return; box.classList.add('open'); inp.setAttribute('aria-expanded', 'true'); draw(''); inp.select(); }
  function close(restore) { stopNaming(); box.classList.remove('open'); inp.setAttribute('aria-expanded', 'false'); if (restore !== false) inp.value = sel.value; }
  function pick(t) {
    if (t === NEW) { if (typed && !newDeckProblem(typed)) { close(false); inp.blur(); addNewDeck(typed); } else startNaming(typed); return; }
    close(false); inp.value = t; inp.blur();
    if (t !== sel.value) { sel.value = t; selectTab(t); } else refreshSheetCombos();
  }
  function createTyped() {
    var n = inp.value.trim().replace(/\s+/g, ' '), why = newDeckProblem(n);
    if (why) { draw(); return; }
    close(false); inp.blur(); addNewDeck(n);
  }
  inp.addEventListener('focus', open);
  inp.addEventListener('mousedown', function() { if (document.activeElement === inp) open(); });
  inp.addEventListener('input', function() { open(); draw(inp.value); });
  inp.addEventListener('keydown', function(k) {
    k.stopPropagation();
    if (naming) {
      if (k.key === 'Enter') { k.preventDefault(); createTyped(); }
      else if (k.key === 'Escape') { k.preventDefault(); stopNaming(); inp.value = ''; draw(''); }
      return;
    }
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
  // Unsaved changes stay in this browser (layouts, cells, settings) — switching sheets keeps them
  if (_gridEdit) endGridEdit(true);
  $('sheetSel').value = name; $('gridSheet').value = name; $('pSheet').value = name; $('eSheet').value = name;
  curTab = name; refreshSheetCombos();
  restorePrintSettings(name); restoreExportSettings(name); selId = null; multi = []; rowIdx = 0; undoStack = []; redoStack = []; GRID = null; _gridSel = null; _rowsStale = false;
  try { localStorage.setItem('cb_tab:' + SHEET_ID, name); } catch (e) {}
  layout = normalize(LAYOUTS[name] ? JSON.parse(JSON.stringify(LAYOUTS[name])) : newLayout());
  setDirty(false);
  _lastHidden = JSON.stringify(layout.hidden || []);
  // An unsaved draft from this browser wins (restored, still marked unsaved)
  try {
    var d = localStorage.getItem('cb_draft:' + SHEET_ID + ':' + name);
    if (d && d !== JSON.stringify(layout)) { layout = normalize(JSON.parse(d)); _lastHidden = JSON.stringify(layout.hidden || []); setDirty(true, true); }
  } catch (e) {}
  $('dataList').innerHTML = '<div class="muted">Loading…</div>';
  $('cardHolder').innerHTML = '<div class="loading" style="width:240px;height:200px"><span class="spin"></span>Loading rows…</div>';
  if (!PUBLISHED[name]) loadPublished(name);
  var shown = false;
  function useRows(rows, partial) {
    ROWS = (rows || []).filter(function(r) { return Object.keys(r).some(function(k) { return !isInternal(k) && String(r[k]).trim(); }); });
    _countTab = name;
    FIELDS = [];
    ROWS.forEach(function(r) { Object.keys(r).forEach(function(k) { if (!isInternal(k) && FIELDS.indexOf(k) < 0) FIELDS.push(k); }); });
    _rowsPartial = !!partial; showLoadStatus();
    overlayPending(partial);   // this browser's unsaved cell edits (and, once every row is in, new rows) on top
    rowIdx = Math.min(rowIdx, Math.max(0, ROWS.length - 1));
    renderData(); if (shown) render(); else fitZoom(); renderAttrs();
    shown = true;
    if (_activeTab === 'print') renderPrint();
    if (_activeTab === 'export') renderExport();
  }
  function allLoaded() {
    setTimeout(keepThumb, 2500);   // the Designs page's picture, if the first card looks different now
    if (!FIELDS.length || FIELDS.length === dataPend(name).cols.length) headerFields(name);   // no cards: the columns come from the heading row
    if (_activeTab === 'data') loadGrid(force);
    var due = false; try { due = !!localStorage.getItem('cb_repub:' + SHEET_ID + ':' + name); localStorage.removeItem('cb_repub:' + SHEET_ID + ':' + name); } catch (e) {}
    if (force) { toast('✓ Reloaded from the sheet'); autoRepublish(name); }
    else if (_republishWaiting === name || due) { _republishWaiting = ''; autoRepublish(name); }
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
// The fields come from the cards' rows, so a deck with columns but no cards yet would have none.
// Then read the heading row (the Cards view's read, kept for it too) and offer its columns.
function headerFields(tab) {
  return api('grid', { tab: tab }).then(function(r) {
    if (curTab !== tab) return;
    var add = (r.header || []).filter(function(h) { return h && !isInternal(h) && FIELDS.indexOf(h) < 0 && !colDeleted(h); });
    if (!GRID || GRID.tab !== tab) { GRID = { tab: tab, header: r.header || [], rows: r.rows || [] }; overlayGrid(); }
    if (!add.length) return;
    FIELDS = add.concat(FIELDS.filter(function(f) { return add.indexOf(f) < 0; }));
    ROWS.forEach(function(row) { add.forEach(function(f) { if (!(f in row)) row[f] = ''; }); });
    renderData(); render(); renderAttrs();
  }).catch(function() {});
}
// Beside Save: "Loading rows…" until every row of the sheet is in
function showLoadStatus() {
  var el = $('loadStatus'); if (!el) return;
  el.hidden = !_rowsPartial;
  el.textContent = !_rowsPartial ? '' : ROWS.length ? 'Loading the other rows…' : 'Loading rows…';
}
// Fetch: the tab list too (tabs added, renamed or removed in Google Sheets), then this tab's rows
function reloadSheet(asked) {
  if (!asked && pendingCount()) {
    askDialog({ title: 'Save before fetching?', message: 'You have unsaved changes. Fetch reads the latest from your Google Sheet — save first so they’re included. If you fetch without saving, your changes stay in this browser and are shown on top of the latest data.',
                ok: 'Save, then fetch', alt: 'Fetch without saving', cancel: 'Cancel' })
      .then(function(a) { if (a === true) saveAll().then(function() { reloadSheet(true); }); else if (a === 'alt') reloadSheet(true); });
    return;
  }
  // Fetch reads everything again from the sheet: what's kept in this browser is forgotten (your unsaved
  // changes stay — they're kept apart) and the page opens fresh
  if (!navigator.onLine) { toast('You’re offline — Fetch needs a connection', true); return; }
  toast('Fetching the latest from your sheet…');
  cacheClear().then(function() { location.reload(); });
}
function openInSheets() { window.open('https://docs.google.com/spreadsheets/d/' + SHEET_ID + '/edit', '_blank', 'noopener'); }

// ── Left panel: FIXED (text, image) · CARD · SHAPE · GENERATED ──────────────────────
function chip(attrs, ico, name, sample, extraCls) {
  return '<div class="field-chip' + (extraCls ? ' ' + extraCls : '') + '" draggable="true" ' + attrs + ' ondragstart="chipDragStart(event)" onclick="chipClick(this, event)" title="' + (/data-field=/.test(attrs) ? 'Drag onto the card · click for options' : 'Drag onto the card') + '">' +
         '<span class="ico">' + ico + '</span><span class="nm">' + esc(name) + '</span>' +
         (sample && sample.pic ? '<span class="sample-pic" style="background-image:url(&quot;' + esc(sample.pic) + '&quot;)"></span>'   // a picture link: its thumbnail
           : sample ? '<span class="sample">' + esc(sample) + '</span>' : '') + '</div>';
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
  h += section('fixed', 'Fixed', chip('data-add="text"', 'T', 'Text') + chip('data-add="image"', IMG_ICON, 'Image'));   // the same on every card
  h += section('data', 'Card', '<div id="dataList"><div class="muted">Loading…</div></div>', '',
    '<button type="button" class="eye-btn" id="eyeBtn" onclick="event.stopPropagation();toggleShowHidden()" style="display:none"></button>');
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
  var list = orderedFields(_hiddenOpen ? FIELDS : shown);
  h += list.map(function(f) {
    return isHidden(f) ? chip('data-field="' + esc(f) + '"', '⊘', f, '', 'is-hidden')
                       : chip('data-field="' + esc(f) + '"', DATA_ICON, f, isImageLink(sample[f]) ? { pic: imageUrl(cleanVal(sample[f]).trim()) } : looksLikeImage(f) ? '' : textVal(sample[f]), f === _newField ? 'just-added' : '');
  }).join('') + nf;
  el.innerHTML = h;
  wireFieldReorder(el);
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
function showNewField() { if (!hasAccess()) { readOnlyWarn(); return; } var b = $('newField'); b.classList.add('open'); $('newFieldName').value = ''; checkNewField(); $('newFieldName').focus(); }
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
  createColumn(n)
    .then(function() {
      _newField = n; renderData();
      var chipEl = document.querySelector('.field-chip[data-field="' + CSS.escape(n) + '"]'); if (chipEl) chipEl.scrollIntoView({ block: 'nearest' });
      setTimeout(function() { if (_newField === n) { _newField = ''; var c = document.querySelector('.field-chip.just-added'); if (c) c.classList.remove('just-added'); } }, 2500);
    })
    .catch(function(e) { $('newFieldMsg').textContent = e.message; });
}
function chipSpec(c) {
  if (c.dataset.field) return 'data:' + c.dataset.field;
  if (c.dataset.shape) return 'shape:' + c.dataset.shape;
  if (c.dataset.gen)   return 'gen:' + c.dataset.gen;
  return 'add:' + c.dataset.add;
}
// Read only: say why nothing happens (instead of asking a viewer to sign in)
function readOnlyWarn() {
  toast(!ACCT ? 'Read only — sign in to design your own cards' : viewOnly() ? 'View only — this sheet belongs to another GameBoard account' : 'Your free week has ended — subscribe to keep designing', true);
}
function chipDragStart(ev) {
  var c = ev.currentTarget;
  if (!hasAccess()) { ev.preventDefault(); readOnlyWarn(); return; }
  ev.dataTransfer.setData('text/plain', chipSpec(c)); ev.dataTransfer.effectAllowed = 'copyMove';
  _dragField = c.dataset.field || '';
  if (_dragField) setTimeout(function() { c.classList.add('dragging'); }, 0);
}
// Data fields keep the same order as the columns in the Cards view (SETTINGS.grid[tab].order).
// Drag a field up or down the list to move it; a line shows where it will go.
var _dragField = '';
function fieldOrder() { var g = SETTINGS.grid && SETTINGS.grid[curTab]; return (g && g.order) || []; }
function orderedFields(list) {
  var order = fieldOrder();
  return list.map(function(f, k) { var o = order.indexOf(f); return { f: f, k: o < 0 ? order.length + k : o }; })
    .sort(function(a, b) { return a.k - b.k; }).map(function(x) { return x.f; });
}
function wireFieldReorder(el) {
  if (el._reorder) return; el._reorder = true;
  function clear() { el.querySelectorAll('.drop-before, .drop-after').forEach(function(c) { c.classList.remove('drop-before', 'drop-after'); }); }
  function spot(y) {   // where the dragged field would go: [chip, before?]
    var chips = [].slice.call(el.querySelectorAll('.field-chip[data-field]'));
    for (var i = 0; i < chips.length; i++) { var r = chips[i].getBoundingClientRect(); if (y < r.top + r.height / 2) return [chips[i], true]; }
    return chips.length ? [chips[chips.length - 1], false] : null;
  }
  el.addEventListener('dragover', function(ev) {
    if (!_dragField) return;
    ev.preventDefault(); ev.dataTransfer.dropEffect = 'move';
    var s = spot(ev.clientY); clear();
    if (s) s[0].classList.add(s[1] ? 'drop-before' : 'drop-after');
  });
  el.addEventListener('dragleave', function(ev) { if (!el.contains(ev.relatedTarget)) clear(); });
  el.addEventListener('drop', function(ev) {
    if (!_dragField) return;
    ev.preventDefault(); ev.stopPropagation();
    var f = _dragField, s = spot(ev.clientY); clear(); _dragField = '';
    if (!s || s[0].dataset.field === f) return;
    var names = [].slice.call(el.querySelectorAll('.field-chip[data-field]')).map(function(c) { return c.dataset.field; });
    var target = names.indexOf(s[0].dataset.field) + (s[1] ? 0 : 1), from = names.indexOf(f);
    if (from < 0 || target === from || target === from + 1) return;
    names.splice(from, 1); names.splice(target > from ? target - 1 : target, 0, f);
    var p = gridPrefs(curTab), rest = orderedFields(FIELDS).filter(function(n) { return names.indexOf(n) < 0; });
    p.order = names.concat(rest);   // fields not in the list (hidden ones) keep their place after
    saveGridPrefs(); renderData();
    if (GRID && GRID.tab === curTab) renderGrid();
  });
}
document.addEventListener('dragend', function() {
  _dragField = '';
  document.querySelectorAll('.field-chip.dragging, .field-chip.drop-before, .field-chip.drop-after').forEach(function(c) { c.classList.remove('dragging', 'drop-before', 'drop-after'); });
});
// Adding is by drag and drop only; clicking a Data field opens its options
function chipClick(c, ev) {
  if (!layout) return;
  if (ev) ev.stopPropagation();
  if (c.dataset.field && hasAccess()) { openFieldMenu(c); return; }
  c.classList.remove('nudge'); void c.offsetWidth; c.classList.add('nudge');   // a little wiggle: drag me
}
function closeFieldMenu() { var m = $('fieldMenu'); if (m) m.remove(); var a = document.querySelector('.field-chip.menu-open'); if (a) a.classList.remove('menu-open'); }
function openFieldMenu(chipEl) {
  var f = chipEl.dataset.field, open = chipEl.classList.contains('menu-open');
  closeFieldMenu(); if (open) return;
  var hidden = isHidden(f), used = layout.els.concat(layout.back.els).some(function(e) { return e.field === f || (e.kind === 'text' && (e.text || '').indexOf('{' + f + '}') >= 0); });
  var m = document.createElement('div'); m.id = 'fieldMenu'; m.className = 'field-menu';
  m.innerHTML = '<button type="button" onclick="setFieldHidden(' + esc(JSON.stringify(f)) + ',' + !hidden + ')">' + (hidden ? '👁 Show field' : '<span class="fm-ico">⊘</span> Hide field') + '</button>' +
    '<p>' + (hidden ? 'Brings it back in Design and Cards.' : 'Hides it here and in the Cards tab. The column stays in the sheet' + (used ? ', and it still shows on the card.' : '.')) + '</p>';
  chipEl.classList.add('menu-open');
  chipEl.parentNode.insertBefore(m, chipEl.nextSibling);
}
document.addEventListener('click', function(ev) { if (!ev.target.closest('#fieldMenu') && !ev.target.closest('.field-chip.menu-open')) closeFieldMenu(); });
// Hidden fields are kept with the layout (so they stay hidden on every device once saved)
function isHidden(f) { return !!(layout && layout.hidden && layout.hidden.indexOf(f) >= 0); }
function visibleFields() { return FIELDS.filter(function(f) { return !isHidden(f); }); }
function setFieldHidden(f, hide) {
  if (!hasAccess()) { readOnlyWarn(); return; }
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
  if (type === 'data' && looksLikeImage(arg)) {
    // A column of picture links: shown as a picture to start with (Show as in the panel changes it)
    e = { kind: 'data', field: arg, as: 'image', fit: 'contain', w: Math.round(c.w * .5), h: Math.round(c.w * .5) };
  } else if (type === 'data') {
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
// BillBoard). Links are kept as links and loaded from their source every time.
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
function picRef(s) {   // a picture address, or the picture of an image tag written [name]
  var m = /^\s*\[([^\[\]]+)\]\s*$/.exec(s || '');
  if (m) { var t = tagsOn() && TAGMAP[tagKey(m[1])]; return t ? tagPicture(t.value) : ''; }
  return s || '';
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
    start = api('grid', { tab: tab }).then(function(r) { if (curTab === tab) { GRID = { tab: tab, header: r.header || [], rows: r.rows || [] }; overlayGrid(); } });
  }
  return start.then(function() {
    var live = GRID.rows.filter(function(g) { return !g.draft && !rowDeleted(g.row); });
    var i = ROWS.indexOf(row), guess = live[i];
    if (guess && gridMatch(like, guess)) return guess;
    var all = live.filter(function(g) { return gridMatch(like, g); });
    if (all.length === 1) return all[0];
    throw new Error(all.length ? 'several rows look the same — set the picture in the Cards tab' : 'couldn\'t find this row in the sheet — try Fetch');
  });
}
function setRowPicture(e, url) {   // the picture's address goes into this row's cell — kept in this browser until Save
  if (!ROWS[rowIdx]) { if (!url) return; newCardRow(); }   // no cards yet: the picture starts the first one
  setRowCell(e, url);
  toast(url ? 'Picture set for this row — Save to put it in the sheet' : 'Picture removed from this row — Save to update the sheet');
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
    (slot != null ? '<p class="muted ip-row">Used when <b>' + esc(e.field) + '</b> is <b>' + esc(((e.map.items[slot] || {}).v) || '…') + '</b>.</p>' : '') +
    (e.kind === 'data' && slot == null ?'<p class="muted ip-row">For <b>row ' + (rowIdx + 1) + '</b> — saved in its <b>' + esc(e.field) + '</b> cell in the sheet. Uploads are kept on zapsheets.com.</p>' : '') +
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
  holder.insertAdjacentHTML('beforeend', alignDotsHTML() + fillBtnsHTML());
  var card = holder.firstChild;
  card.style.transform = 'scale(' + zoom + ')';
  card.style.setProperty('--z', zoom * PX);
  $('rowInfo').innerHTML = ROWS.length ? cardNo(rowIdx + 1) : esc(_rowsPartial ? 'Loading cards…' : 'No cards');
  $('rowInfo').title = ROWS.length ? 'Card ' + (rowIdx + 1) + ' of ' + ROWS.length + ' — click to type a number' + (_rowsPartial ? ' (loading the rest…)' : '') : '';
  updateDeckCount();
  $('rowInfo').classList.toggle('partial', _rowsPartial);
  $('rowPrev').disabled = $('rowFirst').disabled = rowIdx <= 0; $('rowNext').disabled = $('rowLast').disabled = rowIdx >= ROWS.length - 1;
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
  var ku = keyUnit();
  if (ku && units.length > 1) {
    var kids = ku.map(function(x) { return x.id; });
    return { card: false, key: true, units: units.filter(function(u) { return !u.some(function(x) { return kids.indexOf(x.id) >= 0; }); }), ref: selBox(ku) };
  }
  if (units.length > 1) return { card: false, units: units, ref: selBox(sel) };
  return { card: true, units: [sel], ref: { x: 0, y: 0, w: layout.card.w, h: layout.card.h } };
}
var ALIGN_TITLES = {
  key:  { top: 'Line up the tops with the key object', middle: 'Line up the middles with the key object', bottom: 'Line up the bottoms with the key object', left: 'Line up the left edges with the key object', center: 'Line up the centers with the key object', right: 'Line up the right edges with the key object' },
  sel:  { top: 'Line up the tops', middle: 'Line up the middles', bottom: 'Line up the bottoms', left: 'Line up the left edges', center: 'Line up the centers', right: 'Line up the right edges' },
  card: { top: 'Move to the top of the card', middle: 'Center up and down on the card', bottom: 'Move to the bottom of the card', left: 'Move to the left edge of the card', center: 'Center across the card', right: 'Move to the right edge of the card' }
};
function alignDotsHTML() {
  if (!hasAccess()) return '';   // read only: nothing to line up
  var plan = alignPlan(); if (!plan) return '';
  var bb = plan.ref, T = ALIGN_TITLES[plan.card ? 'card' : plan.key ? 'key' : 'sel'], k = PX * zoom, W = layout.card.w * k, H = layout.card.h * k, h = '';
  var ys = { top: bb.y, middle: bb.y + bb.h / 2, bottom: bb.y + bb.h }, xs = { left: bb.x, center: bb.x + bb.w / 2, right: bb.x + bb.w };
  var offR = 34, offB = 34;   // dots sit out past the card's right and bottom edges…
  var CW = W, CH = H;   // the hover guides still run across the whole card
  if (plan.key) { W = Math.round((bb.x + bb.w) * k); H = Math.round((bb.y + bb.h) * k); offR = 26; offB = 26; }   // …or around the key object
  Object.keys(ys).forEach(function(n) {
    var y = Math.round(ys[n] * k);
    h += '<div class="al-guide al-h" style="left:' + W + 'px;top:' + y + 'px;width:' + offR + 'px"></div>' +
      '<button type="button" class="al-dot" style="left:' + (W + offR) + 'px;top:' + y + 'px" onclick="alignSel(\'' + n + '\')" title="' + T[n] + '" aria-label="' + T[n] + '"></button>' +
      '<div class="al-guide al-h in" style="left:0;top:' + y + 'px;width:' + Math.max(W, CW) + 'px"></div>';
  });
  Object.keys(xs).forEach(function(n) {
    var x = Math.round(xs[n] * k);
    h += '<div class="al-guide al-v" style="top:' + H + 'px;left:' + x + 'px;height:' + offB + 'px"></div>' +
      '<button type="button" class="al-dot" style="top:' + (H + offB) + 'px;left:' + x + 'px" onclick="alignSel(\'' + n + '\')" title="' + T[n] + '" aria-label="' + T[n] + '"></button>' +
      '<div class="al-guide al-v in" style="top:0;left:' + x + 'px;height:' + Math.max(H, CH) + 'px"></div>';
  });
  return h;
}
// Full width / full height: round buttons at the middle of the card's top and left edges
var FILL_ICONS = {
  w: '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M7 8l-4 4 4 4M17 8l4 4-4 4"/></svg>',
  h: '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/></svg>'
};
function fillBtnsHTML() {
  if (!selEls().length || !hasAccess()) return '';   // read only: nothing to resize
  var k = PX * zoom, W = layout.card.w * k, H = layout.card.h * k;
  return '<button type="button" class="fill-btn" style="left:' + Math.round(W / 2) + 'px;top:-20px" onclick="fillSel(\'w\')" title="Make the full width of the card" aria-label="Make the full width of the card">' + FILL_ICONS.w + '</button>' +
         '<button type="button" class="fill-btn" style="left:-20px;top:' + Math.round(H / 2) + 'px" onclick="fillSel(\'h\')" title="Make the full height of the card" aria-label="Make the full height of the card">' + FILL_ICONS.h + '</button>';
}
function fillSel(dim) {
  var sel = selEls(); if (!sel.length) return;
  var c = layout.card, done = sel.every(function(e) { return dim === 'w' ? e.x === 0 && e.w === c.w : e.y === 0 && e.h === c.h; });
  if (done) { toast('Already the full ' + (dim === 'w' ? 'width' : 'height')); return; }
  snapshot();
  sel.forEach(function(e) { if (dim === 'w') { e.x = 0; e.w = c.w; } else { e.y = 0; e.h = c.h; } });
  changed(true); var one = elById(selId); if (one) syncGeomInputs(one); else renderAttrs();
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
function firstCard() { stepRow(-ROWS.length); }
function lastCard() { stepRow(ROWS.length); }
// Go to a card: click its number above the card and type another (Enter goes, Esc keeps this one)
function editCardNo() {
  var box = $('rowInfo'); if (!ROWS.length || box.querySelector('input')) return;
  var n = ROWS.length;
  box.innerHTML = '<input class="card-no-in" type="text" inputmode="numeric" aria-label="Go to card" value="' + (rowIdx + 1) + '" style="width:' + (String(n).length + 1.6) + 'ch">';
  var inp = box.querySelector('input'), done = false;
  var finish = function(go) {
    if (done) return; done = true;
    var v = parseInt(inp.value, 10);
    if (go && !isNaN(v)) { rowIdx = clamp(v, 1, n) - 1; stepRow(0); } else render();
  };
  inp.addEventListener('keydown', function(e) {
    e.stopPropagation();   // typed digits are for the box, not the designer's shortcuts
    if (e.key === 'Enter') { e.preventDefault(); finish(true); }
    else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
  });
  inp.addEventListener('blur', function() { finish(true); });
  inp.focus(); inp.select();
}
function stepRow(d) { rowIdx = clamp(rowIdx + d, 0, Math.max(0, ROWS.length - 1)); render(); renderData(); var se = elById(selId); if (se && se.kind === 'data') renderAttrs(); else if (isPicEl(se)) renderAttrs(); }
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
  if (!hasAccess()) return;
  var node = ev.target.closest('.el'), e = node && elById(node.dataset.id);
  if (e && isPicEl(e)) openImagePicker(ev, e.id);
  else if (e && e.kind === 'data') editCellInPlace(e);
  else if (e && e.kind === 'text') editTextInPlace(e);
});
// Double-click a Text element to edit its wording on the card — as written, with {Column} and [tag]
// still showing. Enter saves (Shift+Enter for a new line), Esc cancels, clicking away saves.
function editTextInPlace(e) {
  if (!requirePro('edit', function() {})) return;
  if (!isSel(e.id) || multi.length) setSel([e.id]);
  var node = $('cardHolder').querySelector('.el[data-id="' + e.id + '"]'), inner = node && node.querySelector('.el-inner');
  if (!inner) return;
  var old = e.text || '', done = false;
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
    if (save && v !== old) { snapshot(); e.text = v; changed(); }
    else render();
  }
  inner.addEventListener('keydown', function(k) {
    k.stopPropagation();
    if (k.key === 'Enter' && !k.shiftKey) { k.preventDefault(); finish(true); }
    else if (k.key === 'Escape') { k.preventDefault(); finish(false); }
  });
  inner.addEventListener('blur', function() { finish(true); });
}
// Double-click a Data field shown as text to edit this row's value right on the card.
// Enter saves to the sheet (Shift+Enter for a new line), Esc cancels, clicking away saves.
function editCellInPlace(e) {
  if (!requirePro('edit', function() {})) return;
  var row = ROWS[rowIdx], fresh = !row;   // no cards yet: typing here makes the first one
  if (!isSel(e.id) || multi.length) setSel([e.id]);
  var node = $('cardHolder').querySelector('.el[data-id="' + e.id + '"]'), inner = node && node.querySelector('.el-inner');
  if (!inner) return;
  var old = fresh ? '' : cleanVal(row[e.field]), done = false;
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
    if (!save || v === old || (fresh && !v.trim())) { render(); return; }
    if (fresh || !ROWS[rowIdx]) newCardRow();
    setRowCell(e, v);
  }
  inner.addEventListener('keydown', function(k) {
    k.stopPropagation();
    if (k.key === 'Enter' && !k.shiftKey) { k.preventDefault(); finish(true); }
    else if (k.key === 'Escape') { k.preventDefault(); finish(false); }
  });
  inner.addEventListener('blur', function() { finish(true); });
}
// + Card beside the card navigator: a new, empty card straight after the one on screen. It's kept
// once something is typed into it (an empty row isn't a card), and Save puts its row in the sheet in
// that place: under the card before it (or at the top), after a new card it follows.
function addCard() {
  if (!layout || !curTab) return;
  if (!requirePro('edit', function() {})) return;
  var cur = ROWS[rowIdx], next = ROWS[rowIdx + 1];
  var blank = function(r) { return r && r._pend && !FIELDS.some(function(f) { return String(r[f] || '').trim(); }); };
  if (blank(cur)) { toast('This card is still empty — fill it in first'); return; }
  if (blank(next)) { stepRow(1); return; }
  var at = ROWS.length ? rowIdx + 1 : 0, tab = curTab;
  var r = newCardRow(at), prev = ROWS[at - 1];
  if (prev && prev._pend) { r._prevKey = prev._pend; if (prev._after) r._after = prev._after; }
  else if (prev && at < ROWS.length - 1) {   // under a card that's in the sheet (at the end it's simply added last)
    var like = Object.assign({}, prev);
    sheetRowFor(prev, like).then(function(gr) {
      if (!gr || !gr.row) return;
      r._after = gr.row;
      var pd = dataPend(tab), a = pd.adds.filter(function(x) { return x.key === r._pend; })[0];   // already typed into: place it now
      if (a && !a.after) { a.after = gr.row; setDataPend(tab, pd); }
    }).catch(function() {});
  }
  render(); renderData(); renderAttrs();
  if (_activeTab === 'print') renderPrint(); if (_activeTab === 'export') renderExport();
  toast('New card ' + (rowIdx + 1) + ' — double-click a field on the card to fill it in');
}
// A new card (a new row, kept in this browser until Save), shown in the designer straight away
function newCardRow(at) {   // at: its place in the deck (default: the end)
  var r = { _pend: uid() }; FIELDS.forEach(function(f) { r[f] = ''; });
  at = at == null ? ROWS.length : clamp(at, 0, ROWS.length);
  ROWS.splice(at, 0, r); rowIdx = at;   // the Cards view adds it (from the saved-for-later rows) when it's next shown
  return r;
}
// Write a text value into this row's cell for a Data field. Shown on the card straight away;
// put back if the sheet can't be updated.
function setRowValue(v) {
  var e = elById(selId), row = ROWS[rowIdx]; if (!e || e.kind !== 'data') return;
  if (!requirePro('edit', function() {})) { renderAttrs(); return; }
  if (!row) { if (!String(v).trim()) return; newCardRow(); setRowCell(e, v); return; }   // the first card
  if (String(row[e.field] == null ? '' : row[e.field]) !== v) setRowCell(e, v);
}
function setRowCell(e, value) {   // kept in this browser until Save
  var row = ROWS[rowIdx]; if (!row) return;
  var tab = curTab, field = e.field, before = row[field];
  if (row._pend) {   // a new row that isn't in the sheet yet
    row[field] = value; var o = {}; FIELDS.forEach(function(f) { o[f] = row[f] || ''; }); queueAdd(tab, row._pend, o, { after: row._after, prevKey: row._prevKey });
    if (GRID && GRID.tab === tab) { var gd = GRID.rows.filter(function(g) { return g.key === row._pend; })[0], c0 = GRID.header.indexOf(field); if (gd && c0 >= 0) { gd.values[c0] = value; (gd.state = gd.state || {})[c0] = 'pending'; } }
    render(); renderData(); if (selId === e.id) renderAttrs(); return;
  }
  var like = Object.assign({}, row); like[field] = before;
  row[field] = value; render(); renderData();
  sheetRowFor(row, like).then(function(gr) {
    var c = GRID.header.indexOf(field); if (c < 0) throw new Error('the ' + field + ' column wasn\'t found');
    var was = pendingOld(tab, gr.row, field), sheetVal = was != null ? was : (gr.values[c] == null ? '' : gr.values[c]);
    if (/^=/.test(String(sheetVal))) throw new Error('that cell is a formula — change it in the sheet');
    queueCell(tab, gr.row, field, value, sheetVal);
    gr.values[c] = value; (gr.state = gr.state || {})[c] = pendingOld(tab, gr.row, field) == null ? '' : 'pending';
    if (_activeTab === 'data') renderGrid(); if (selId === e.id) renderAttrs();
  }).catch(function(err) {
    if (row[field] === value) row[field] = before;
    render(); renderData();
    toast('Couldn\'t change it — ' + err.message, true);
  });
}
// Move / resize elements on the card
$('cardHolder').addEventListener('pointerdown', function(ev) {
  if (ev.target.closest('.img-btn') || ev.target.closest('.el.editing')) return;
  var elDom = ev.target.closest('.el'); if (!elDom || ev.button > 0) return;
  var e = elById(elDom.dataset.id); if (!e) return;
  ev.preventDefault();
  var handle = ev.target.dataset.h || '';
  if (!hasAccess()) {   // read only: clicking selects (to see its attributes), nothing moves
    var u = e.group ? groupMembers(e.group).map(function(x) { return x.id; }) : [e.id];
    if (!u.every(isSel) || multi.length) setSel(u);
    return;
  }
  if (handle === 'rot' && isSel(e.id) && !multi.length) { rotateDrag(ev, e, elDom); return; }   // before Shift-click, so Shift can snap the angle
  // Clicking part of a group picks the whole group; Shift/⌘-click adds to (or takes from) the selection
  var unit = e.group ? groupMembers(e.group).map(function(x) { return x.id; }) : [e.id];
  if (ev.shiftKey || ev.metaKey || ev.ctrlKey) {
    var cur = selIdsNow(), has = unit.every(function(id) { return cur.indexOf(id) >= 0; });
    setSel(has ? cur.filter(function(id) { return unit.indexOf(id) < 0; }) : cur.concat(unit));
    return;
  }
  var clickedSel = unit.every(isSel);
  if (!clickedSel) setSel(unit);
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
      $('cardHolder').querySelectorAll('.el-list').forEach(function(l) {   // a list group moves from its corner
        var lb = selBox(groupMembers(l.dataset.g)); if (lb) { l.style.left = lb.x + 'pt'; l.style.top = lb.y + 'pt'; }
      });
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
  var wasSel = group && unit.every(function(id) { return group.some(function(x) { return x.id === id; }); }) && multi.length > unit.length;
  function up() {
    document.removeEventListener('pointermove', mv); document.removeEventListener('pointerup', up);
    $('cardHolder').classList.remove('moving');
    if (moved) changed(!group);   // a group's panel shows its position, so refresh it
    else if (wasSel && clickedSel) { keyId = keyId === e.id || (keyUnit() || []).some(function(x) { return x.id === e.id; }) ? null : e.id; render(); renderAttrs(); }   // pick (or drop) the key object
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
document.addEventListener('keydown', function(ev) {   // ⌘Z / ⇧⌘Z in the Cards view (not while typing in a cell)
  if (_activeTab !== 'data' || !(ev.metaKey || ev.ctrlKey) || ev.key.toLowerCase() !== 'z') return;
  var a = document.activeElement; if (a && (/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || a.isContentEditable)) return;
  ev.preventDefault(); ev.stopPropagation(); dataUndo(ev.shiftKey);
}, true);
document.addEventListener('keydown', function(ev) {
  var typing = /^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '') || !!(document.activeElement && document.activeElement.isContentEditable);
  var mod = ev.metaKey || ev.ctrlKey;
  if (mod && ev.key.toLowerCase() === 's') { ev.preventDefault(); saveAll(); return; }
  if (!typing && _activeTab === 'design' && layout && ev.key === 'Escape' && selEls().length) { selectEl(null); return; }
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
  rememberMaps();
  setDirty(true);
  if (layout && JSON.stringify(layout.hidden || []) !== _lastHidden) { _lastHidden = JSON.stringify(layout.hidden || []); renderData(); if (GRID && _activeTab === 'data') renderGrid(); }
  lsPut('cb_draft:' + SHEET_ID + ':' + curTab, JSON.stringify(layout));
  render();
  if (!keepAttrs) renderAttrs(); else renderLayers();
}
function setDirty(d, restored) {
  dirty = d;
  updateSaveBtn();
}

// ── Saving: every change is kept in this browser until Save ──────────────────
// Layouts (cb_draft:…), cell edits and new rows (cb_data:…) and settings (cb_settings:…) are kept in
// local storage, so they survive switching sheets, reloading or closing the window. Save sends them
// all to the sheet in the background — you can keep working, and anything changed meanwhile stays
// for the next Save.
function lsGet(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
var _lsWarned = false;
function lsSet(k, v) { lsPut(k, v == null ? null : JSON.stringify(v)); }
function lsPut(k, str) {   // a raw string (or null to remove); if storage is full, save to the sheet to make room
  try { if (str == null) localStorage.removeItem(k); else localStorage.setItem(k, str); }
  catch (e) {
    if (!_lsWarned) { _lsWarned = true; toast('This browser’s storage is full — saving to your sheet to make room', true); setTimeout(function() { _lsWarned = false; }, 15000); }
    autoSave(true); return false;
  }
  storageCheck(); return true;
}
// Browsers give a site about 5 MB here (Safari counts it as ~2.5 million characters, so that's the budget).
// When CardBoard's own data passes 60% of it, everything is saved to the sheet in the background.
var LS_BUDGET = 2500000, LS_AUTO = 0.6, _storageTimer = null;
function storageUsed() {
  var n = 0;
  try { for (var i = 0; i < localStorage.length; i++) { var k = localStorage.key(i); n += k.length + (localStorage.getItem(k) || '').length; } } catch (e) {}
  return n;
}
function storageCheck() {
  clearTimeout(_storageTimer);
  _storageTimer = setTimeout(function() { if (storageUsed() > LS_BUDGET * LS_AUTO) autoSave(false); }, 800);
}
function autoSave(full) {
  if (_saving || !pendingCount() || !hasAccess()) return;
  toast(full ? 'Storage is full — saving your changes to the sheet' : 'This browser’s storage is running low — saving your changes to the sheet in the background');
  saveAll(true);
}
function lsTabs(kind) {   // tabs with something kept under cb_<kind>:<sheet>:<tab>
  var pre = 'cb_' + kind + ':' + SHEET_ID + ':', out = [];
  try { for (var i = 0; i < localStorage.length; i++) { var k = localStorage.key(i); if (k && k.indexOf(pre) === 0) out.push(k.slice(pre.length)); } } catch (e) {}
  return out;
}
function draftTabs() {
  var list = lsTabs('draft'); if (curTab && layout && dirty && list.indexOf(curTab) < 0) list.push(curTab);
  return list.filter(function(t) {
    var d = null; try { d = localStorage.getItem('cb_draft:' + SHEET_ID + ':' + t); } catch (e) {}
    if (t === curTab && layout) return dirty;
    if (!d || (LAYOUTS[t] && d === JSON.stringify(LAYOUTS[t]))) { try { localStorage.removeItem('cb_draft:' + SHEET_ID + ':' + t); } catch (e) {} return false; }
    return true;
  });
}
function dataKey(tab) { return 'cb_data:' + SHEET_ID + ':' + tab; }
function dataPend(tab) { var p = lsGet(dataKey(tab)) || {}; ['cells', 'adds', 'cols', 'delrows', 'delcols'].forEach(function(k) { p[k] = p[k] || []; }); return p; }
function pendSize(p) { return p.cells.length + p.adds.length + p.cols.length + p.delrows.length + p.delcols.length + (p.order && p.order.length ? 1 : 0); }
// Cards moved in the Cards view: p.order is the sheet rows holding cards, in their new order. Until Save,
// the grid and the cards (Layout, Print, Export) show that order; Save moves the rows in the sheet first.
function sortSlots(arr, movable, rank) {   // reorder the movable items among the places they hold; the rest stay put
  var at = [], items = [];
  arr.forEach(function(x, i) { if (movable(x)) { at.push(i); items.push(x); } });
  items.sort(function(a, b) { return rank(a) - rank(b); });
  at.forEach(function(i, k) { arr[i] = items[k]; });
}
function orderRank(order) { return function(row) { var k = order.indexOf(row); return k < 0 ? 1e6 + row : k; }; }
// The cards (ROWS) come in sheet order without their row numbers; the grid has them. While the cards are still
// in sheet order (before any are dropped or moved), the n-th card is the n-th sheet row that holds a card.
function numberCards(tab) {
  if (tab !== curTab || !GRID || GRID.tab !== tab) return;
  var mine = ROWS.filter(function(r) { return !r._pend; });
  if (!mine.length || mine.every(function(r) { return r._row; })) return;
  var nums = GRID.rows.filter(function(g) { return !g.draft; }).map(function(g) { return g.row; }).sort(function(a, b) { return a - b; });
  if (ROWS._delDone) { var del = dataPend(tab).delrows.map(function(d) { return d.row; }); nums = nums.filter(function(n) { return del.indexOf(n) < 0; }); }   // cards deleted here are gone already
  if (nums.length !== mine.length) return;
  mine.forEach(function(r, i) { r._row = nums[i]; });
}
function applyRowOrder(tab) {
  var o = dataPend(tab).order, rank = orderRank(o || []);
  numberCards(tab);
  if (!o || !o.length) return;
  if (tab === curTab) sortSlots(ROWS, function(r) { return !r._pend && r._row; }, function(r) { return rank(r._row); });
  if (GRID && GRID.tab === tab) sortSlots(GRID.rows, function(g) { return !g.draft; }, function(g) { return rank(g.row); });
}
function setDataPend(tab, p) {
  if (!_dUndoQuiet) dataUndoMark(tab);
  lsSet(dataKey(tab), pendSize(p) ? p : null); updateSaveBtn();
}
// ── Undo / Redo in the Cards view ─────────────────────────────────────────────
// Every change to the cards is kept in this browser until Save (cell edits, new and deleted rows and
// columns), so a step back is the kept changes as they were before, shown again on top of the sheet's
// data (kept here too, so no reading). Changes made in one go (a paste, a delete) are one step.
// Save clears the steps: what's saved is in the sheet now.
var _dUndo = {}, _dRedo = {}, _dUndoQuiet = false, _dUndoOpen = {};
function dataSnap(tab) { return JSON.stringify({ p: lsGet(dataKey(tab)), g: ((SETTINGS.grid || {})[tab]) || null }); }
function dataUndoMark(tab) {
  if (_dUndoOpen[tab]) return;   // already marked for this change
  _dUndoOpen[tab] = true; setTimeout(function() { _dUndoOpen[tab] = false; }, 0);
  var u = _dUndo[tab] = _dUndo[tab] || [];
  u.push(dataSnap(tab)); if (u.length > 100) u.shift();
  _dRedo[tab] = [];
  updateDataUndoBtns();
}
function dataUndoClear(tab) { if (tab) { delete _dUndo[tab]; delete _dRedo[tab]; } else { _dUndo = {}; _dRedo = {}; } updateDataUndoBtns(); }
function updateDataUndoBtns() {
  var u = $('gridUndo'), r = $('gridRedo'); if (!u || !r) return;
  u.disabled = !(_dUndo[curTab] || []).length || _saving; r.disabled = !(_dRedo[curTab] || []).length || _saving;
}
function dataUndo(redo) {
  var tab = curTab, from = redo ? _dRedo[tab] : _dUndo[tab], to = redo ? (_dUndo[tab] = _dUndo[tab] || []) : (_dRedo[tab] = _dRedo[tab] || []);
  if (!from || !from.length || _saving) return;
  if (_gridEdit) endGridEdit(false);
  to.push(dataSnap(tab));
  var snap = JSON.parse(from.pop());
  _dUndoQuiet = true;
  lsSet(dataKey(tab), snap.p);
  var g = SETTINGS.grid = SETTINGS.grid || {};
  if (JSON.stringify(g[tab] || null) !== JSON.stringify(snap.g)) { if (snap.g) g[tab] = snap.g; else delete g[tab]; saveGridPrefs(); }
  _dUndoQuiet = false;
  updateSaveBtn(); updateDataUndoBtns();
  reapplyPending(tab);
}
// The sheet's data (as kept) with this browser's changes on top again — for the grid and the cards
function reapplyPending(tab) {
  return Promise.all([api('grid', { tab: tab }), api('rows', { tab: tab })]).then(function(r) {
    if (curTab !== tab) return;
    GRID = { tab: tab, header: r[0].header || [], rows: r[0].rows || [] };
    ROWS = (r[1].rows || []).filter(function(x) { return Object.keys(x).some(function(k) { return !isInternal(k) && String(x[k]).trim(); }); });
    FIELDS = []; ROWS.forEach(function(x) { Object.keys(x).forEach(function(k) { if (!isInternal(k) && FIELDS.indexOf(k) < 0) FIELDS.push(k); }); });
    GRID.header.forEach(function(h) { if (h && !isInternal(h) && FIELDS.indexOf(h) < 0) FIELDS.push(h); });
    overlayPending(); overlayGrid();
    rowIdx = Math.min(rowIdx, Math.max(0, ROWS.length - 1));
    if (_gridSel && (!GRID.rows[_gridSel.r] || _gridSel.c >= GRID.header.length + 1)) _gridSel = null;
    if (_activeTab === 'data') renderGrid();
    renderData(); render(); renderAttrs();
  }).catch(function(e) { toast('Couldn\'t show that step — ' + e.message, true); });
}
function dataTabs() { return lsTabs('data').filter(function(t) { return pendSize(dataPend(t)) > 0; }); }
function rowDeleted(row) { return !!GRID && dataPend(GRID.tab).delrows.some(function(d) { return d.row === row; }); }
function colDeleted(name) { return !!name && !!curTab && dataPend(curTab).delcols.indexOf(name) >= 0; }
function pendingCol(tab, name) { return dataPend(tab).cols.indexOf(name) >= 0; }
function settingsPending() { return !!lsGet('cb_settings:' + SHEET_ID); }
function markSettings() { var keep = Object.assign({}, SETTINGS); delete keep.profile; lsSet('cb_settings:' + SHEET_ID, keep); updateSaveBtn(); }
function pendingCount() {
  var n = newTabs().length + draftTabs().length + (settingsPending() ? 1 : 0);
  dataTabs().forEach(function(t) { n += pendSize(dataPend(t)); });
  return n;
}
var _saving = false, _savingP = null, _saveSteps = 0, _saveDone = 0, _quietSave = false;
// Save by itself as rarely as possible: changes pile up in this browser (where they're safe — closing the
// page keeps them) and go to the sheet in one batch once the person has stopped for a good while
// (IDLE_SAVE_MS, nothing mid-edit). Changes that have waited a long time (LONG_WAIT_MS) go at a shorter
// pause (LONG_IDLE_MS), so a long session still reaches the sheet. Switching to another tab or app isn't
// a save by itself — it simply counts as being away. Save (⌘S) still saves at once.
var IDLE_SAVE_MS = 90000, LONG_WAIT_MS = 15 * 60000, LONG_IDLE_MS = 20000, _lastActivity = Date.now(), _pendingSince = 0;
['keydown', 'pointerdown', 'input', 'drop', 'wheel'].forEach(function(t) { document.addEventListener(t, function() { _lastActivity = Date.now(); }, true); });
function busyEditing() {
  if (_gridEdit || document.querySelector('.el.editing, #imgPicker, #arrFly')) return true;
  var a = document.activeElement;
  return !!a && (/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || a.isContentEditable);
}
function idleSave(force) {
  if (!pendingCount()) { _pendingSince = 0; return; }
  if (!_pendingSince) _pendingSince = Date.now();
  if (_saving || !navigator.onLine || !hasAccess()) return;
  var idle = Date.now() - _lastActivity, waited = Date.now() - _pendingSince;
  if (!force && (busyEditing() || idle < (waited > LONG_WAIT_MS ? LONG_IDLE_MS : IDLE_SAVE_MS))) return;
  _quietSave = true; _pendingSince = 0; saveAll(true);
}
setInterval(idleSave, 5000);
window.addEventListener('offline', function() { toast('You’re offline — keep working. Changes stay in this browser until you save.'); });
window.addEventListener('online', function() { toast('Back online' + (pendingCount() ? ' — your changes will be saved once you take a break' : '')); });
// The page itself, its fonts and the card pictures are kept too (cardboard-sw.js), so it opens offline
if ('serviceWorker' in navigator) navigator.serviceWorker.register(APP_BASE + 'cardboard-sw.js', { scope: APP_BASE + SHEET_ID + '/cardboard' }).catch(function() {});
function updateSaveBtn() {
  updateDataUndoBtns();
  var btn = $('saveBtn'), n = pendingCount(); if (!btn) return;
  btn.disabled = _saving || !n;
  btn.classList.toggle('saving', _saving);
  // While saving, the button fills up as each part (a tab's data, a layout, settings) is saved
  var pct = _saving ? Math.max(8, Math.round(100 * (_saveDone + .35) / Math.max(1, _saveSteps))) : 0;
  btn.style.setProperty('--sv', Math.min(100, pct) + '%');
  btn.innerHTML = _saving ? '<span class="sv-fill"></span><span class="sv-txt"><span class="sv-spin"></span>Saving</span>' : 'Save';
  btn.title = _saving ? 'Saving to your Google Sheet — keep working' : n ? 'Save your changes to the sheet now (⌘S) — otherwise they’re saved together after you’ve stopped for a minute or two' : 'Everything is saved';
  var st = $('saveStatus');
  if (st && st.dataset.prev == null) { st.textContent = _saving ? 'Saving…' : ''; st.className = 'tb-status'; }
}
// Cell edits: one entry per cell, keeping the value the sheet had before the first edit (to spot edits made in Sheets meanwhile)
function queueCell(tab, row, field, value, old) {
  var p = dataPend(tab), c = p.cells.filter(function(x) { return x.row === row && x.field === field; })[0];
  if (c) { c.value = value; if (c.value === c.old) p.cells.splice(p.cells.indexOf(c), 1); }
  else if (value !== old) p.cells.push({ row: row, field: field, value: value, old: old == null ? '' : old });
  setDataPend(tab, p);
}
// place: {after: sheet row it goes under (1 = the top), prevKey: a new card it follows} — where + Card put it
function queueAdd(tab, key, values, place) {
  var p = dataPend(tab), a = p.adds.filter(function(x) { return x.key === key; })[0];
  var empty = !Object.keys(values).some(function(k) { return String(values[k]).trim(); });
  if (a) { if (empty) p.adds.splice(p.adds.indexOf(a), 1); else a.values = values; }
  else if (!empty) {
    var na = { key: key, values: values }; if (place && place.after >= 1) na.after = place.after;
    var prev = place && place.prevKey ? p.adds.filter(function(x) { return x.key === place.prevKey; })[0] : null;
    if (prev) { if (prev.after) na.after = prev.after; else delete na.after; p.adds.splice(p.adds.indexOf(prev) + 1, 0, na); }   // straight after the new card it follows
    else p.adds.push(na);
  }
  setDataPend(tab, p);
}
function pendingOld(tab, row, field) { var c = dataPend(tab).cells.filter(function(x) { return x.row === row && x.field === field; })[0]; return c ? c.old : null; }
// Show this tab's unsaved cell edits and new rows on top of what was read from the sheet
function overlayPending(partial) {
  var tab = curTab, p = dataPend(tab);
  applyRowOrder(tab);
  p.cols.forEach(function(n) { if (FIELDS.indexOf(n) < 0) FIELDS.push(n); ROWS.forEach(function(r) { if (!(n in r)) r[n] = ''; }); });   // columns not in the sheet yet
  p.delcols.forEach(function(n) { FIELDS = FIELDS.filter(function(f) { return f !== n; }); ROWS.forEach(function(r) { delete r[n]; }); });   // columns deleted here
  if (!partial) p.adds.forEach(function(a) {   // new rows become cards at the end
    if (ROWS.some(function(r) { return r._pend === a.key; })) return;
    var r = { _pend: a.key }; FIELDS.forEach(function(f) { r[f] = a.values[f] || ''; }); ROWS.push(r);
  });
  if (!p.cells.length && !p.delrows.length && !(p.order && p.order.length)) { overlayGrid(); return; }   // moved cards need the grid's row numbers
  var go = function() { if (curTab !== tab || !GRID) return; overlayGrid(); render(); renderData(); if (_activeTab === 'data') renderGrid(); };
  if (GRID && GRID.tab === tab) go();
  else api('grid', { tab: tab }).then(function(r) { if (curTab !== tab || (GRID && GRID.tab === tab)) return go(); GRID = { tab: tab, header: r.header || [], rows: r.rows || [] }; go(); }).catch(function() {});
}
function gridCardIndex(gr) { return GRID.rows.filter(function(g) { return !g.draft && !rowDeleted(g.row); }).indexOf(gr); }   // which card a sheet row is
function overlayGrid() {
  if (!GRID || GRID.tab !== curTab) return;
  var p = dataPend(curTab);
  applyRowOrder(curTab);
  p.cols.forEach(function(n) {
    if (GRID.header.indexOf(n) >= 0) return;
    var at = GRID.header.length; while (at > 0 && !GRID.header[at - 1]) at--;
    GRID.header[at] = n; GRID.rows.forEach(function(r) { while (r.values.length < GRID.header.length) r.values.push(''); });
  });
  // Rows deleted here: their cards go (once for each set of rows read from the sheet)
  if (p.delrows.length && !ROWS._delDone) {
    var all = GRID.rows.filter(function(g) { return !g.draft; }), drop = [];
    p.delrows.forEach(function(d) { var k = all.findIndex(function(g) { return g.row === d.row; }); if (k >= 0 && k < ROWS.length) drop.push(k); });
    drop.sort(function(a, b) { return b - a; }).forEach(function(k) { ROWS.splice(k, 1); });
    ROWS._delDone = true; rowIdx = Math.min(rowIdx, Math.max(0, ROWS.length - 1));
  }
  p.cells.forEach(function(c) {
    var gr = GRID.rows.filter(function(g) { return !g.draft && g.row === c.row; })[0], col = GRID.header.indexOf(c.field);
    if (!gr || col < 0) return;
    gr.values[col] = c.value; (gr.state = gr.state || {})[col] = 'pending';
    var card = ROWS[gridCardIndex(gr)]; if (card && !card._pend) card[c.field] = c.value;
  });
  var tail = GRID.rows.length;   // new rows go above the empty row(s) kept at the end for typing into
  while (tail > 0 && GRID.rows[tail - 1].draft && rowEmpty(GRID.rows[tail - 1]) && !p.adds.some(function(a) { return a.key === GRID.rows[tail - 1].key; })) tail--;
  // A card added after another one sits under that card's row (after the new cards already there),
  // the same place it will have in the sheet; the others go at the end
  p.adds.forEach(function(a) {
    var have = GRID.rows.filter(function(g) { return g.key === a.key; })[0];
    if (have && !a.after) return;
    var st = {}, gr = have || { row: 0, draft: true, key: a.key, values: GRID.header.map(function(h, i) { if (h && a.values[h]) st[i] = 'pending'; return h ? (a.values[h] || '') : ''; }), state: st };
    if (!a.after) { GRID.rows.splice(tail++, 0, gr); return; }
    if (have) { var hi = GRID.rows.indexOf(have); GRID.rows.splice(hi, 1); if (hi < tail) tail--; }
    var at = a.after <= 1 ? 0 : GRID.rows.findIndex(function(g) { return !g.draft && g.row === a.after; }) + 1;
    if (a.after > 1 && at <= 0) at = tail;   // that row isn't here any more: at the end
    while (at < tail && GRID.rows[at].draft && p.adds.some(function(x) { return x.key === GRID.rows[at].key && x.after === a.after; })) at++;
    GRID.rows.splice(at, 0, gr); if (at <= tail) tail++;
  });
}
// Save everything kept in this browser, one tab at a time, in the background
function saveAll(auto) {
  if (_saving) return _savingP;
  if (!pendingCount()) { if (auto !== true) toast('Everything is already saved'); return Promise.resolve(); }
  if (!navigator.onLine) { if (auto !== true) toast('You’re offline — your changes are kept in this browser. Save again when you’re back online.', true); return Promise.resolve(); }
  if (!requirePro('save', saveAll)) return Promise.resolve();
  if (_gridEdit && auto !== true) endGridEdit(true);
  _saving = true; _saveDone = 0;
  _saveSteps = newTabs().length + dataTabs().length + draftTabs().length + (settingsPending() ? 1 : 0);
  updateSaveBtn();
  var step = function() { _saveDone++; updateSaveBtn(); };
  var errors = [], conflicts = 0, savedTabs = [], chain = Promise.resolve(), failedNew = [];
  // New decks first: the tab has to be in the spreadsheet before its columns, cards and layout
  newTabs().forEach(function(tab) {
    chain = chain.then(function() {
      return api('addtab', { name: tab }).then(function() {
        setNewTabs(newTabs().filter(function(x) { return x !== tab; }));
        TABS.forEach(function(t) { if (t.title === tab) delete t.pending; });
      }).catch(function(e) { failedNew.push(tab); errors.push('new deck “' + tab + '”: ' + e.message); }).then(step);
    });
  });
  dataTabs().forEach(function(tab) {
    chain = chain.then(function() {
      if (isNewTab(tab)) { step(); return; }   // its tab couldn't be added: keep its changes for the next Save
      var sent = dataPend(tab), moved = Promise.resolve();
      if (sent.order && sent.order.length) moved = api('reorder', { tab: tab, order: JSON.stringify(sent.order) }).then(function(res) {
        var map = res.map || {}, to = function(r) { return map[String(r)] || r; }, p = dataPend(tab);
        p.cells.forEach(function(x) { x.row = to(x.row); });
        p.delrows.forEach(function(x) { x.row = to(x.row); });
        p.adds.forEach(function(a) { if (a.after > 1) a.after = to(a.after); });
        if (p.order && JSON.stringify(p.order) !== JSON.stringify(sent.order)) p.order = p.order.map(to); else delete p.order;   // moved again while saving: next time
        _dUndoQuiet = true; setDataPend(tab, p); _dUndoQuiet = false;
        _reloadGrid[tab] = true; _lastSaveData[tab] = true; if (savedTabs.indexOf(tab) < 0) savedTabs.push(tab);
        sent = dataPend(tab);
      });
      return moved.then(function() {
        if (!pendSize(sent) || (sent.order && sent.order.length)) return;
        return api('savecells', { tab: tab, cols: JSON.stringify(sent.cols), cells: JSON.stringify(sent.cells), adds: JSON.stringify(sent.adds), delrows: JSON.stringify(sent.delrows), delcols: JSON.stringify(sent.delcols) })
        .then(function(res) { conflicts += applySaveResults(tab, sent, res); if (savedTabs.indexOf(tab) < 0) savedTabs.push(tab); });
      }).catch(function(e) { errors.push('“' + tab + '” data: ' + e.message); }).then(step);
    });
  });
  draftTabs().forEach(function(tab) {
    chain = chain.then(function() {
      if (isNewTab(tab)) { step(); return; }
      var snap = tab === curTab && layout ? JSON.stringify(layout) : localStorage.getItem('cb_draft:' + SHEET_ID + ':' + tab);
      if (!snap) { step(); return; }
      return api('save', { tab: tab, layout: snap }).then(function() {
        LAYOUTS[tab] = JSON.parse(snap);
        var now = tab === curTab && layout ? JSON.stringify(layout) : localStorage.getItem('cb_draft:' + SHEET_ID + ':' + tab);
        if (now === snap) { try { localStorage.removeItem('cb_draft:' + SHEET_ID + ':' + tab); } catch (e) {} if (tab === curTab) dirty = false; }
        if (savedTabs.indexOf(tab) < 0) savedTabs.push(tab);
      }).catch(function(e) { errors.push('“' + tab + '” layout: ' + e.message); }).then(step);
    });
  });
  if (settingsPending()) chain = chain.then(function() {
    var snap = JSON.stringify(lsGet('cb_settings:' + SHEET_ID));
    return api('save', { tab: SETTINGS_KEY, layout: snap }).then(function() {
      LAYOUTS[SETTINGS_KEY] = JSON.parse(snap); _oldProfile = null;
      if (JSON.stringify(lsGet('cb_settings:' + SHEET_ID)) === snap) lsSet('cb_settings:' + SHEET_ID, null);
    }).catch(function(e) { errors.push('settings: ' + e.message); }).then(step);
  });
  _savingP = chain.then(function() {
    _saving = false; refreshSheetCombos(); updateSaveBtn(); writeStartCache(); dataUndoClear();
    // Hosted links show the saved cards: update them now for this tab, the others when they're next opened
    savedTabs.forEach(function(t) { if (t !== curTab && PUBLISHED[t]) { try { localStorage.setItem('cb_repub:' + SHEET_ID + ':' + t, '1'); } catch (e) {} } });
    if (_reloadGrid[curTab] && GRID && GRID.tab === curTab) { GRID = null; _gridSel = null; if (_activeTab === 'data') loadGrid(true); }   // rows or columns were deleted: numbers changed
    if (savedTabs.indexOf(curTab) >= 0) { if (_lastSaveData[curTab]) refreshRows(); else autoRepublish(curTab); }
    _lastSaveData = {}; _reloadGrid = {};
    setTimeout(keepThumb, 2500);   // the Designs page's picture (skipped while the rows reload; done when they're in)
    if (errors.length) toast('Couldn’t save everything — ' + errors[0] + (errors.length > 1 ? ' (and ' + (errors.length - 1) + ' more)' : '') + '. Your changes are still in this browser; try Save again.', true);
    else if (conflicts) toast('Saved — but ' + conflicts + ' cell' + (conflicts === 1 ? ' was' : 's were') + ' changed in the sheet meanwhile, so the sheet’s value' + (conflicts === 1 ? ' was' : 's were') + ' kept (marked red)', true);
    else if (!_quietSave) toast(auto === true ? '✓ Saved to your Google Sheet in the background' : '✓ Saved to your Google Sheet');
    _quietSave = false;
    storageCheck();
  });
  return _savingP;
}
var _lastSaveData = {}, _reloadGrid = {};
// What the sheet said about each saved cell; anything edited again during the save stays pending
function applySaveResults(tab, sent, res) {
  var p = dataPend(tab), conflicts = 0, g = GRID && GRID.tab === tab ? GRID : null;
  _lastSaveData[tab] = true;
  p.cols = p.cols.filter(function(n) { return sent.cols.indexOf(n) < 0; });   // those headings are in the sheet now
  (res.results || []).forEach(function(r) {
    var s0 = sent.cells.filter(function(x) { return x.row === r.row && x.field === r.field; })[0]; if (!s0) return;
    var cur = p.cells.filter(function(x) { return x.row === r.row && x.field === r.field; })[0];
    var col = g ? g.header.indexOf(r.field) : -1, gr = g ? g.rows.filter(function(x) { return !x.draft && x.row === r.row; })[0] : null;
    if (r.status === 'ok') {
      if (cur && cur.value === s0.value) p.cells.splice(p.cells.indexOf(cur), 1);
      else if (cur) cur.old = r.value != null ? r.value : s0.value;   // edited again meanwhile: still pending, on top of what's now saved
      if (gr && col >= 0 && !(cur && cur.value !== s0.value)) { gr.values[col] = r.value != null ? r.value : s0.value; (gr.state = gr.state || {})[col] = 'saved'; }
    } else {
      if (r.status === 'conflict') conflicts++;
      if (cur && cur.value === s0.value) p.cells.splice(p.cells.indexOf(cur), 1);
      if (gr && col >= 0) { gr.values[col] = r.current != null ? r.current : s0.old; (gr.state = gr.state || {})[col] = 'error'; }
    }
  });
  (res.added || []).forEach(function(ad) {
    var s0 = sent.adds.filter(function(x) { return x.key === ad.key; })[0], cur = p.adds.filter(function(x) { return x.key === ad.key; })[0];
    if (cur) {
      p.adds.splice(p.adds.indexOf(cur), 1);
      // edited again while saving: the differences become cell edits on the new row
      if (s0 && ad.row) Object.keys(cur.values).forEach(function(f) { if ((cur.values[f] || '') !== (s0.values[f] || '')) p.cells.push({ row: ad.row, field: f, value: cur.values[f], old: s0.values[f] || '' }); });
    }
    var gr = g ? g.rows.filter(function(x) { return x.key === ad.key; })[0] : null;
    if (gr && ad.row) { gr.draft = false; gr.row = ad.row; delete gr.key; gr.state = {}; }
    var card = tab === curTab ? ROWS.filter(function(r) { return r._pend === ad.key; })[0] : null; if (card) delete card._pend;
  });
  // Deletions: done (or, for a row someone changed meanwhile, skipped). Rows below a deleted one move up.
  p.delcols = p.delcols.filter(function(n) { return sent.delcols.indexOf(n) < 0; });
  p.delrows = p.delrows.filter(function(d) { return !sent.delrows.some(function(x) { return x.row === d.row; }); });
  var gone = (res.deleted_rows || []).slice().sort(function(a, b) { return a - b; });
  var ins = (sent.adds || []).map(function(a) { return +a.after || 0; }).filter(function(x) { return x >= 1; });   // cards put in under a row
  if (gone.length || ins.length) {
    var shift = function(row) { return row + ins.filter(function(x) { return x < row; }).length - gone.filter(function(x) { return x < row; }).length; };
    p.cells.forEach(function(x) { x.row = shift(x.row); });
    p.delrows.forEach(function(x) { x.row = shift(x.row); });
  }
  conflicts += (res.row_conflicts || []).length;
  if (gone.length || ins.length || (res.deleted_cols || []).length || (res.row_conflicts || []).length) _reloadGrid[tab] = true;
  setDataPend(tab, p);
  if (_reloadGrid[tab]) return conflicts;   // the grid is read again after the save
  if (g && _activeTab === 'data' && curTab === tab) {
    renderGrid();
    setTimeout(function() { if (!GRID || GRID !== g) return; g.rows.forEach(function(r) { Object.keys(r.state || {}).forEach(function(c) { if (r.state[c] === 'saved') delete r.state[c]; }); }); if (_activeTab === 'data') renderGrid(); }, 1200);
  }
  return conflicts;
}
window.addEventListener('beforeunload', function(ev) { if (_saving) { ev.preventDefault(); ev.returnValue = ''; } });   // only while a save is on its way

// ── Copy, cut and paste — between sides, tabs and spreadsheets ───────────────
// The copied elements (with their groups) are kept in this browser, and also put on the
// system clipboard so a paste in another CardBoard window works too.
var CLIP_MARK = 'cardboard-elements:';
function clipData() {
  var sel = selEls().sort(function(a, b) { return (a.z || 1) - (b.z || 1); }); if (!sel.length) return null;
  var gids = []; sel.forEach(function(e) { if (e.group && gids.indexOf(e.group) < 0) gids.push(e.group); });
  return { v: 1, from: curTab + ':' + side, els: JSON.parse(JSON.stringify(sel)),
           groups: gids.map(function(g) { return groupById(g); }).filter(Boolean).map(function(g) { return JSON.parse(JSON.stringify(g)); }) };
}
function storeClip(d) { try { localStorage.setItem('cb_clip', JSON.stringify(d)); } catch (e) {} }
function readClip(text) {
  if (text != null) {
    if (text.indexOf(CLIP_MARK) !== 0) return null;
    try { var t = JSON.parse(text.slice(CLIP_MARK.length)); return t && t.els && t.els.length ? t : null; } catch (e) { return null; }
  }
  try { var d = JSON.parse(localStorage.getItem('cb_clip') || 'null'); return d && d.els && d.els.length ? d : null; } catch (e) { return null; }
}
function copySel(ev) {
  var d = clipData(); if (!d) return false;
  storeClip(d);
  var txt = CLIP_MARK + JSON.stringify(d);
  if (ev && ev.clipboardData) { ev.clipboardData.setData('text/plain', txt); ev.preventDefault(); }
  else if (navigator.clipboard) navigator.clipboard.writeText(txt).catch(function() {});
  toast('Copied ' + d.els.length + ' element' + (d.els.length === 1 ? '' : 's') + ' — paste into any layout');
  return true;
}
function cutSel(ev) { if (!hasAccess()) return; if (copySel(ev)) deleteSel(); }
function pasteClip(d) {
  d = d || readClip(); if (!d || !layout) return;
  if (!requirePro('edit', function() { pasteClip(d); })) return;
  snapshot();
  var els = curEls(), c = layout.card, top = els.reduce(function(m, x) { return Math.max(m, x.z || 1); }, 0), gmap = {}, ids = [];
  // Offset when it would land exactly on what's already there (pasting again in the same place)
  var off = 0;
  while (d.els.some(function(e) { return els.some(function(x) { return x.kind === e.kind && Math.abs(x.x - (e.x + off)) < .01 && Math.abs(x.y - (e.y + off)) < .01; }); }) && off < 200) off += 8;
  var missing = [];
  d.els.forEach(function(e) {
    var n = JSON.parse(JSON.stringify(e)); n.id = uid(); n.z = ++top;
    n.x = r1(clamp(n.x + off, -n.w + 6, c.w - 6)); n.y = r1(clamp(n.y + off, -n.h + 6, c.h - 6));   // keep it on this card, which may be smaller
    if (n.kind === 'gen') n.seed = uid();
    if (n.group) {
      if (!gmap[n.group]) { var g = (d.groups || []).filter(function(x) { return x.id === n.group; })[0] || { name: 'Group' }; var gid = newGroup(g.name); var ng = groupById(gid); if (g.list) ng.list = g.list; if (g.gap != null) ng.gap = g.gap; if (g.align) ng.align = g.align; gmap[n.group] = gid; }
      n.group = gmap[n.group];
    }
    if (n.kind === 'data' && FIELDS.length && FIELDS.indexOf(n.field) < 0 && missing.indexOf(n.field) < 0) missing.push(n.field);
    els.push(n); ids.push(n.id);
  });
  cleanGroups(); normalizeZ(); setSelQuiet(ids); changed();
  toast('Pasted ' + ids.length + ' element' + (ids.length === 1 ? '' : 's') + (missing.length ? ' — this sheet has no ' + missing.map(function(f) { return '“' + f + '”'; }).join(', ') + ' column' : ''), !!missing.length);
}
function clipTarget() { return _activeTab === 'design' && layout && !/^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '') && !(document.activeElement && document.activeElement.isContentEditable); }
document.addEventListener('copy', function(ev) { if (clipTarget() && selEls().length && !String(window.getSelection() || '')) copySel(ev); });
document.addEventListener('cut', function(ev) { if (clipTarget() && selEls().length && !String(window.getSelection() || '')) cutSel(ev); });
document.addEventListener('paste', function(ev) {
  if (!clipTarget()) return;
  var text = ev.clipboardData ? ev.clipboardData.getData('text/plain') : '';
  var d = readClip(text) || (!text && !(ev.clipboardData && ev.clipboardData.files && ev.clipboardData.files.length) ? readClip() : null);
  if (!d) return;   // not ours: a picture link or file is handled by the image paste
  ev.preventDefault(); ev.stopImmediatePropagation(); pasteClip(d);
}, true);
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
  if (keyId && (ids.length < 2 || ids.indexOf(keyId) < 0)) keyId = null;
}
// The key object: with several selected, click one of them (without dragging) and the others line up to it
var keyId = null;
function keyUnit() {
  if (!keyId || multi.length < 2 || multi.indexOf(keyId) < 0) return null;
  var e = elById(keyId); if (!e) return null;
  if (e.group && groupMembers(e.group).every(function(m) { return isSel(m.id); })) return groupMembers(e.group);
  return [e];
}
function setSel(ids) { closeImagePicker(); setSelQuiet(ids); render(); renderAttrs(); }
function fixSel() { setSelQuiet(selIdsNow()); }
function selBox(els) {
  if (!els.length) return null;
  var x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
  els.forEach(function(e) { x1 = Math.min(x1, e.x); y1 = Math.min(y1, e.y); x2 = Math.max(x2, e.x + e.w); y2 = Math.max(y2, e.y + e.h); });
  // A list group is as big as it's drawn on the card (it fits this row's contents), from its top-left corner
  var gid = els[0].group, lg = gid && els.every(function(e) { return e.group === gid; }) && groupMembers(gid).length === els.length && groupById(gid);
  var node = lg && lg.list && $('cardHolder') && $('cardHolder').querySelector('.el-list[data-g="' + gid + '"]');
  if (node && zoom) { var r = node.getBoundingClientRect(), k = zoom * PX; return { x: r1(x1), y: r1(y1), w: r1(r.width / k), h: r1(r.height / k) }; }
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
  var t = { text: 'T', data: dataIsImage(e) ? IMG_ICON : DATA_ICON, image: IMG_ICON, gen: '#' }[e.kind] || '•';
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
  if (!g) { var ku = keyUnit(); h += '<p class="muted" style="margin:.35rem 0 0">' + (ku ? 'Lining up to <b>' + esc(ku.length > 1 && ku[0].group ? (groupById(ku[0].group) || { name: 'the group' }).name : elLabel(ku[0])) + '</b> — the dots around it line the others up with it. Click it again to line up with each other.' : 'Click one of them to make it the <b>key object</b>: the others line up with it.') + '</p>'; }
  if (g) {
    var mode = g.list === 'h' || g.list === 'v' ? g.list : '';
    // The list buttons: click to choose, or press and hold for the alignments (slide to one and let go).
    // The chosen list shows its alignment in its icon.
    function btn(o) {
      var lst = o[0] === 'h' || o[0] === 'v', ico = lst && o[0] === mode ? ALIGN_ICONS[mode][listAlign(g)] : LIST_ICONS[o[0] || 'free'];
      return '<button type="button" class="' + (o[0] === mode ? 'on' : '') + (lst ? ' arr-hold' : '') + '" title="' + o[1] + (lst ? ' · press and hold to align' : '') + '" aria-label="' + o[1].split(':')[0] + '"' +
        (lst ? ' onpointerdown="arrPress(event,\'' + gid + '\',\'' + o[0] + '\')" onclick="if(!event.detail)setGroupList(\'' + gid + '\',\'' + o[0] + '\')"'
             : ' onclick="setGroupList(\'' + gid + '\',\'' + o[0] + '\')"') + '>' + ico + '</button>';
    }
    // One row: Free on its own, then the two lists with their Gap
    h += '<span class="lbl">Arrange</span><div class="arr-row">' +
      '<div class="seg arr-free">' + btn(['', 'Free: members stay where you put them']) + '</div>' +
      '<div class="arr-list' + (mode ? ' on' : '') + '"><div class="seg">' + btn(['h', 'Horizontal list: side by side, each only as wide as it needs']) + btn(['v', 'Vertical list: one under another, each only as tall as it needs']) + '</div>' +
      '<label class="arr-gap" title="Space between the members of the list (pt)"><span>Gap</span><input class="inp" type="number" step=".5" min="0" id="g_gap" value="' + r1(g.gap == null ? 4 : g.gap) + '"' + (mode ? '' : ' disabled') + ' onchange="setGroupGap(\'' + gid + '\',this.value)"></label></div></div>';
    if (mode) h += '<p class="muted" style="margin:.35rem 0 0">In the Layers order, top first — drag there to reorder. Empty text and pictures take no room.</p>';
  }
  if (g) h += groupWhenAttrs(gid, g);
  h += actionsHTML(g ? 'ungroup' : 'group');
  h += '<span class="lbl">Shortcuts</span><p class="muted" style="margin:0">⌘G group · ⇧⌘G ungroup · ⌘A select all · Shift or ⌘-click to add to the selection</p>';
  return h;
}
var LIST_ICONS = {
  free: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><rect x="3" y="4" width="8" height="6" rx="1.5"/><rect x="13" y="9" width="8" height="5" rx="1.5" opacity=".7"/><rect x="6" y="15" width="7" height="5" rx="1.5" opacity=".45"/></svg>',
  h: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><rect x="2" y="8" width="6" height="8" rx="1.5"/><rect x="9.5" y="8" width="5" height="8" rx="1.5" opacity=".7"/><rect x="16" y="8" width="6" height="8" rx="1.5" opacity=".45"/></svg>',
  v: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><rect x="6" y="2" width="12" height="5.5" rx="1.5"/><rect x="6" y="9.25" width="9" height="5.5" rx="1.5" opacity=".7"/><rect x="6" y="16.5" width="11" height="5.5" rx="1.5" opacity=".45"/></svg>'
};
// ── A group's Mapping: show it only on cards whose value in a column is one of its values ──
function groupShows(g, row) {
  var w = g && g.when; if (!w || !w.field || !row) return true;
  var v = cleanVal(row[w.field]).trim().toLowerCase();
  return (w.values || []).some(function(x) { return String(x).trim().toLowerCase() === v; });
}
function groupWhenAttrs(gid, g) {
  var w = g.when, h = '<span class="lbl">Show as</span><div class="seg">' +
    '<button type="button" class="' + (w ? '' : 'on') + '" title="The group shows on every card" onclick="setGroupWhen(\'' + gid + '\',false)">Fixed</button>' +
    '<button type="button" class="' + (w ? 'on' : '') + '" title="Show the group only on cards whose value in a column is one you pick" onclick="setGroupWhen(\'' + gid + '\',true)">Mapping</button></div>';
  if (!w) return h;
  h += '<div class="map-box" style="--n:2">';
  var known = FIELDS.indexOf(w.field) >= 0;
  h += '<span class="mini">Column</span><select class="sel" onchange="setGroupWhenField(\'' + gid + '\',this.value)">' +
       FIELDS.map(function(f) { return '<option' + (f === w.field ? ' selected' : '') + '>' + esc(f) + '</option>'; }).join('') + (known ? '' : '<option selected>' + esc(w.field) + '</option>') + '</select>';
  var vals = [], seen = {};
  (w.values || []).concat(ROWS.map(function(r) { return cleanVal(r[w.field]).trim(); })).forEach(function(v) {
    var k = String(v).trim().toLowerCase(); if (!k || seen[k] || vals.length >= 80) return; seen[k] = 1; vals.push(String(v).trim());
  });
  var on = (w.values || []).map(function(x) { return String(x).trim().toLowerCase(); });
  h += '<span class="mini" style="margin-top:.45rem">Show when ' + esc(w.field) + ' is</span><div class="when-vals">' +
       (vals.length ? vals.map(function(v) { return '<button type="button" class="when-v' + (on.indexOf(v.toLowerCase()) >= 0 ? ' on' : '') + '" data-v="' + esc(v) + '" onclick="toggleGroupWhenVal(\'' + gid + '\',this.dataset.v)">' + esc(v) + '</button>'; }).join('')
                    : '<span class="muted">No values in this column yet.</span>') + '</div>';
  var row = ROWS[rowIdx];
  h += '<p class="muted" style="margin:.35rem 0 0">' + (!on.length ? 'Pick one or more values — until then the group shows on no card.'
       : row ? 'This card’s ' + esc(w.field) + ' is <b>' + esc(cleanVal(row[w.field]).trim() || 'empty') + '</b>, so the group ' + (groupShows(g, row) ? 'shows.' : 'is hidden (faint here while you design).') : '') + '</p>';
  return h + '</div>';
}
function setGroupWhen(gid, on) {
  var g = groupById(gid); if (!g || !!g.when === on) return;
  snapshot();
  if (on) { var f = mapColumnGuess(), cur = ROWS[rowIdx] ? cleanVal(ROWS[rowIdx][f]).trim() : ''; g.when = { field: f, values: cur ? [cur] : [] }; }
  else delete g.when;
  changed(); renderAttrs();
}
function setGroupWhenField(gid, f) {
  var g = groupById(gid); if (!g || !g.when || g.when.field === f) return;
  snapshot(); var cur = ROWS[rowIdx] ? cleanVal(ROWS[rowIdx][f]).trim() : '';
  g.when = { field: f, values: cur ? [cur] : [] }; changed(); renderAttrs();
}
function toggleGroupWhenVal(gid, v) {
  var g = groupById(gid); if (!g || !g.when) return;
  snapshot(); var k = v.toLowerCase(), l = (g.when.values || []).filter(function(x) { return String(x).toLowerCase() !== k; });
  if (l.length === (g.when.values || []).length) l.push(v);
  g.when.values = l; changed(); renderAttrs();
}
function setGroupList(gid, mode) {
  var g = groupById(gid); if (!g || (g.list || '') === mode) return;
  snapshot(); if (mode) g.list = mode; else delete g.list;
  changed(); renderAttrs();
}
// Alignment across the list: a vertical list lines its members up left / center / right,
// a horizontal one top / middle / bottom. Defaults: vertical → left, horizontal → middle.
function listAlign(g) { return g.align || (g.list === 'h' ? 'center' : 'start'); }
function setGroupListAlign(gid, mode, a) {
  var g = groupById(gid); if (!g || (g.list === mode && listAlign(g) === a)) return;
  snapshot(); g.list = mode; g.align = a; changed(); renderAttrs();
}
// Press and hold a list button: the alignments open in a column below it
var _arr = null;
function closeArrFly() { var f = $('arrFly'); if (f) f.remove(); }
function arrPress(ev, gid, mode) {
  if (ev.button) return;
  ev.preventDefault();
  closeArrFly();
  var b = ev.currentTarget, g = groupById(gid); if (!g) return;
  _arr = { gid: gid, mode: mode, btn: b, fly: null, hi: '' };
  _arr.timer = setTimeout(function() {
    if (!_arr) return;
    var names = mode === 'v' ? ['Left', 'Center', 'Right'] : ['Top', 'Middle', 'Bottom'], cur = g.list === mode ? listAlign(g) : '';
    var f = document.createElement('div'); f.id = 'arrFly'; f.className = 'arr-fly';
    f.innerHTML = ['start', 'center', 'end'].map(function(a, i) {
      return '<button type="button" data-a="' + a + '" class="' + (a === cur ? 'on' : '') + '" title="' + names[i] + '">' + ALIGN_ICONS[mode][a] + '<span>' + names[i] + '</span></button>';
    }).join('');
    f.querySelectorAll('button').forEach(function(x) { x.onclick = function() { closeArrFly(); setGroupListAlign(gid, mode, x.dataset.a); }; });
    document.body.appendChild(f);
    var r = b.getBoundingClientRect();
    f.style.left = Math.min(r.left, innerWidth - f.offsetWidth - 8) + 'px';
    f.style.top = Math.min(r.bottom + 4, innerHeight - f.offsetHeight - 8) + 'px';
    b.classList.add('holding');
    _arr.fly = f;
  }, 320);
}
document.addEventListener('pointermove', function(ev) {
  if (!_arr || !_arr.fly) return;
  var t = document.elementFromPoint(ev.clientX, ev.clientY), hit = t && t.closest('#arrFly button');
  _arr.hi = hit ? hit.dataset.a : '';
  _arr.fly.querySelectorAll('button').forEach(function(x) { x.classList.toggle('hi', x === hit); });
});
document.addEventListener('pointerup', function(ev) {
  if (!_arr) return;
  var a = _arr; _arr = null; clearTimeout(a.timer); a.btn.classList.remove('holding');
  if (!a.fly) { setGroupList(a.gid, a.mode); return; }   // a quick click: just the list
  if (a.hi) { closeArrFly(); setGroupListAlign(a.gid, a.mode, a.hi); return; }
  // Let go on the button itself: keep the column open to click one; anywhere else closes it
  var t = document.elementFromPoint(ev.clientX, ev.clientY);
  if (!(t && a.btn.contains(t))) closeArrFly();
});
document.addEventListener('pointerdown', function(ev) { if ($('arrFly') && !ev.target.closest('#arrFly') && !ev.target.closest('.arr-hold')) closeArrFly(); });
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeArrFly(); });
function alignIco(rects) { return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor">' + rects + '</svg>'; }
var ALIGN_ICONS = {
  v: {
    start:  alignIco('<rect x="3" y="2" width="1.6" height="20" rx=".8" opacity=".5"/><rect x="6" y="5" width="14" height="5" rx="1.5"/><rect x="6" y="14" width="9" height="5" rx="1.5" opacity=".7"/>'),
    center: alignIco('<rect x="11.2" y="2" width="1.6" height="20" rx=".8" opacity=".5"/><rect x="5" y="5" width="14" height="5" rx="1.5"/><rect x="7.5" y="14" width="9" height="5" rx="1.5" opacity=".7"/>'),
    end:    alignIco('<rect x="19.4" y="2" width="1.6" height="20" rx=".8" opacity=".5"/><rect x="4" y="5" width="14" height="5" rx="1.5"/><rect x="9" y="14" width="9" height="5" rx="1.5" opacity=".7"/>')
  },
  h: {
    start:  alignIco('<rect x="2" y="3" width="20" height="1.6" rx=".8" opacity=".5"/><rect x="5" y="6" width="5" height="14" rx="1.5"/><rect x="14" y="6" width="5" height="9" rx="1.5" opacity=".7"/>'),
    center: alignIco('<rect x="2" y="11.2" width="20" height="1.6" rx=".8" opacity=".5"/><rect x="5" y="5" width="5" height="14" rx="1.5"/><rect x="14" y="7.5" width="5" height="9" rx="1.5" opacity=".7"/>'),
    end:    alignIco('<rect x="2" y="19.4" width="20" height="1.6" rx=".8" opacity=".5"/><rect x="5" y="4" width="5" height="14" rx="1.5"/><rect x="14" y="9" width="5" height="9" rx="1.5" opacity=".7"/>')
  }
};
function setGroupGap(gid, v) {
  var g = groupById(gid); v = parseFloat(v); if (!g || isNaN(v)) return;
  snapshot(); g.gap = Math.max(0, v); changed(true);
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
  if (key === 'field' && e.kind === 'data' && e.as === 'map') e.map = savedMap(val);   // the new column's own mappings
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
  if (!/^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test(v)) return;
  var r = recentColors().filter(function(x) { return x.toLowerCase() !== v.toLowerCase(); }); r.unshift(v);
  try { localStorage.setItem('cb_recentColors', JSON.stringify(r.slice(0, 12))); } catch (e) {}
}
function closeColorPop() { var p = $('colorPop'); if (p) p.remove(); }
// Light enough that it needs a dark background to be seen
function isLight(c) {
  var p = colorParts(c); if (!p) return false;
  var n = parseInt(p.hex.slice(1), 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.72;
}
// Colors are #rrggbb, or #rrggbbaa when see-through (the Opacity slider)
function colorParts(v) {
  v = String(v || '').trim();
  if (/^#[0-9a-f]{3}$/i.test(v)) v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
  var m = /^#([0-9a-f]{6})([0-9a-f]{2})?$/i.exec(v);
  return m ? { hex: '#' + m[1].toLowerCase(), a: m[2] ? parseInt(m[2], 16) / 255 : 1 } : null;
}
function withAlpha(hex, a) {
  var p = colorParts(hex); if (!p) return hex;
  a = Math.max(0, Math.min(1, a));
  return a >= .999 ? p.hex : p.hex + ('0' + Math.round(a * 255).toString(16)).slice(-2);
}
function openColorPop(btn, onSet, key, none) {
  var open = $('colorPop') && $('colorPop').dataset.key === onSet + ':' + key; closeColorPop(); if (open) return;
  var target = liveTarget(onSet), cur = target ? String(target[key] || '') : '';
  var cp = colorParts(cur), alpha = cp ? cp.a : 1, base = cp ? cp.hex : '';
  var pick = function(v) { closeColorPop(); if (v && v !== 'transparent') rememberColor(v); window[onSet](key, v); };
  var tile = function(c) { var cc = colorParts(c); return '<button type="button" class="cp-tile' + (cp && cc && cc.hex === cp.hex ? ' on' : '') + '" style="--c:' + c + '" data-c="' + c + '" title="' + c + '"></button>'; };
  var rec = recentColors();
  var p = document.createElement('div'); p.id = 'colorPop'; p.className = 'color-pop'; p.dataset.key = onSet + ':' + key;
  p.innerHTML =
    '<button type="button" class="cp-clear' + ((none ? !cur || cur === 'transparent' : cur === 'transparent') ? ' on' : '') + '" data-c="' + (none ? '' : 'transparent') + '"><span class="cp-tile clear"></span>Transparent</button>' +
    '<div class="cp-grid">' + PALETTE.map(tile).join('') + '</div>' +
    (rec.length ? '<span class="mini">Recent</span><div class="cp-grid">' + rec.map(tile).join('') + '</div>' : '') +
    '<span class="mini">Opacity</span><div class="cp-alpha"><input type="range" min="0" max="100" step="1" value="' + Math.round(alpha * 100) + '"><b>' + Math.round(alpha * 100) + '%</b></div>' +
    '<div class="cp-custom"><label class="cp-more"><input type="color" value="' + (base || '#7b6cf6') + '">Custom color…</label></div>';
  document.body.appendChild(p);
  var r = btn.getBoundingClientRect();
  p.style.left = Math.max(8, Math.min(r.left, innerWidth - p.offsetWidth - 8)) + 'px';
  p.style.top = (r.bottom + p.offsetHeight + 8 > innerHeight ? Math.max(8, r.top - p.offsetHeight - 6) : r.bottom + 6) + 'px';
  var rng = p.querySelector('.cp-alpha input'), pct = p.querySelector('.cp-alpha b'), nat = p.querySelector('input[type=color]');
  function paintRange() { rng.style.setProperty('--c', base || nat.value); }
  paintRange();
  function show(v) { btn.style.setProperty('--c', v); btn.classList.remove('clear'); }
  // a palette color keeps the opacity chosen below it
  p.addEventListener('click', function(ev) { var t = ev.target.closest('[data-c]'); if (!t) return; var c = t.dataset.c; pick(colorParts(c) ? withAlpha(c, alpha) : c); });
  rng.addEventListener('input', function() {
    alpha = rng.value / 100; pct.textContent = rng.value + '%';
    var v = withAlpha(base || nat.value, alpha); liveColor(onSet, key, v); show(v);
  });
  rng.addEventListener('change', function() { var v = withAlpha(base || nat.value, alpha); if (_live && _live.obj === liveTarget(onSet)) endLive(liveTarget(onSet), key); rememberColor(v); window[onSet](key, v); });
  nat.addEventListener('input', function() { base = nat.value; paintRange(); var v = withAlpha(base, alpha); liveColor(onSet, key, v); show(v); });
  nat.addEventListener('change', function() { pick(withAlpha(nat.value, alpha)); });
}
document.addEventListener('pointerdown', function(ev) { var p = $('colorPop'); if (p && !p.contains(ev.target) && !ev.target.closest('.swatch')) closeColorPop(); });
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeColorPop(); });
function numInp(id, label, val, onSet, key, step) {
  return '<div><span class="mini">' + label + '</span><input class="inp" type="number" step="' + (step || 1) + '" id="' + id + '" value="' + r1(val || 0) + '" onchange="' + onSet + '(\'' + key + '\',this.value,true)"></div>';
}
function txtInp(label, key, val, ph) {
  return '<div><span class="mini">' + label + '</span><input class="inp" value="' + esc(val || '') + '" placeholder="' + esc(ph || '') + '" onchange="setProp(\'' + key + '\',this.value)"></div>';
}
// A Text or Image element can become a Mapping: each card's value in a column picks its text or picture.
// It becomes a data element (so it has Show as Text / Image / Mapping like any column) and remembers
// what it was, so "Same on every card" turns it back.
function mapColumnGuess() {   // the column with the fewest different values (a type, a color, a suit…)
  var best = '', bestN = 1e9;
  FIELDS.filter(function(f) { return !isHidden(f) && !looksLikeImage(f); }).forEach(function(f) {
    var seen = {}; ROWS.forEach(function(r) { var v = cleanVal(r[f]).trim(); if (v) seen[v.toLowerCase()] = 1; });
    var n = Object.keys(seen).length; if (n > 1 && n < bestN) { best = f; bestN = n; }
  });
  return best || FIELDS[0] || '';
}
function toMapping() {
  var e = elById(selId); if (!e || (e.kind !== 'text' && e.kind !== 'image')) return;
  if (!FIELDS.length) { toast('Add a column to the sheet first — a Mapping picks by a column’s value', true); return; }
  snapshot();
  e.was = e.kind === 'text' ? { kind: 'text', text: e.text } : { kind: 'image', src: e.src };
  var f = mapColumnGuess();
  e.kind = 'data'; e.field = f; e.as = 'map'; e.map = savedMap(f);
  if (e.was.kind === 'image' && !e.fit) e.fit = 'contain';
  delete e.text; delete e.src;
  changed();
}
function fromMapping() {
  var e = elById(selId); if (!e || !e.was) return;
  snapshot();
  var w = e.was; e.kind = w.kind;
  if (w.kind === 'text') e.text = w.text || ''; else e.src = w.src || '';
  delete e.field; delete e.as; delete e.map; delete e.was;
  changed();
}
function showSeg(cur, fixedLabel) {   // Text / Image elements: the same on every card, or a Mapping
  return '<span class="lbl">Show as</span><div class="seg">' +
    '<button type="button" class="' + (cur === 'fixed' ? 'on' : '') + '" title="The same on every card" onclick="' + (cur === 'fixed' ? '' : 'fromMapping()') + '">' + fixedLabel + '</button>' +
    '<button type="button" class="' + (cur === 'map' ? 'on' : '') + '" title="Each card’s value in a column picks its text or picture" onclick="' + (cur === 'map' ? '' : 'toMapping()') + '">Mapping</button></div>';
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
  return '<span class="lbl">Fit</span>' + seg('fit', [['cover', 'Fill', 'Fill the box (crops)'], ['contain', 'Fit', 'Show the whole image'], ['stretch', 'Stretch', 'Stretch to the box']], e.fit || 'cover');
}
// The Actions section: the same buttons in the same places, with Paste among them (greyed until
// something's been copied). extra: 'group' / 'ungroup' for a selection of several, 'paste' alone for the card
function actionsHTML(extra) {
  var clip = readClip(), n = clip ? clip.els.length : 0;
  var b = function(on, label, title, cls, off) { return '<button class="attr-btn' + (cls ? ' ' + cls : '') + '" onclick="' + on + '"' + (title ? ' title="' + title + '"' : '') + (off ? ' disabled' : '') + '>' + label + '</button>'; };
  var h = '<span class="lbl">Actions</span><div class="act-grid">';
  if (extra === 'paste') return h + b('pasteClip()', 'Paste ' + n + (n === 1 ? ' element' : ' elements'), '⌘V', 'wide') + '</div>';
  if (extra === 'group') h += b('groupSel()', 'Group', '⌘G', 'wide');
  if (extra === 'ungroup') h += b('ungroupSel()', 'Ungroup', '⇧⌘G', 'wide');
  h += b('reorderSel(1)', 'To front') + b('reorderSel(-1)', 'To back') +
       b('copySel()', 'Copy', '⌘C — paste into any layout with ⌘V') + b('cutSel()', 'Cut', '⌘X') +
       b('pasteClip()', n > 1 ? 'Paste ' + n : 'Paste', n ? '⌘V' : 'Copy or cut something first', '', !n) + b('duplicateSel()', 'Duplicate', '⌘D') +
       b('deleteSel()', 'Delete', 'Delete or Backspace', 'danger wide');
  return h + '</div>';
}
// Read only (signed out, someone else's sheet, week over): the attributes still show, nothing in them can be changed
function lockAttrs() {
  if (hasAccess()) return;
  var p = $('attrPanel'); if (!p) return;
  p.querySelectorAll('input,select,textarea,button').forEach(function(x) { x.disabled = true; x.tabIndex = -1; });
  p.querySelectorAll('[contenteditable]').forEach(function(x) { x.contentEditable = 'false'; });
}
function renderAttrs() { _renderAttrs(); lockAttrs(); }
function _renderAttrs() {
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
    if (hasAccess() && readClip()) h += actionsHTML('paste');
    p.innerHTML = h; return;
  }
  var title = e.kind === 'data' ? e.field : e.kind === 'shape' ? (SHAPES.filter(function(s) { return s[0] === e.shape; })[0] || ['', 'Shape'])[1]
            : e.kind === 'gen' ? (GENS.filter(function(g) { return g[0] === e.mode; })[0] || ['', '', 'Generated'])[2] : KIND_NAME[e.kind];
  h += '<p class="attr-title">' + esc(title) + ' <span class="kind">' + KIND_NAME[e.kind] + '</span></p>';

  if (e.kind === 'text') {
    h += '<span class="lbl">Text</span><textarea class="ta" id="a_text" oninput="liveText(this.value)" onchange="setProp(\'text\',this.value)">' + esc(e.text) + '</textarea>';
    h += showSeg('fixed', 'Fixed');
    h += '<div class="insert-row"><select class="sel" id="a_ins"><option value="">Insert a column…</option>' + FIELDS.map(function(f) { return '<option>' + esc(f) + '</option>'; }).join('') + '</select>' +
         '<button class="attr-btn" style="flex:0 0 auto;padding:0 .7rem" onclick="insertField()">Insert</button></div>';
    h += textAttrs(e) + boxAttrs(e, 'Background');
  }
  if (e.kind === 'data') {
    var known = FIELDS.indexOf(e.field) >= 0;
    h += '<span class="lbl">Column</span><select class="sel" onchange="setProp(\'field\',this.value);renderAttrs()">' +
         FIELDS.map(function(f) { return '<option' + (f === e.field ? ' selected' : '') + '>' + esc(f) + '</option>'; }).join('') +
         (known ? '' : '<option selected>' + esc(e.field) + '</option>') + '</select>';
    h += '<span class="lbl">Show as</span>' + seg('as', [['text', 'Text', 'Show the value as text'], ['image', 'Image', 'The value is a picture link'], ['map', 'Mapping', 'Each value shows a text or picture you choose']], e.as || 'text')
      .replace('<div class="seg">', '<div class="seg">' + (e.was ? '<button type="button" title="The same on every card" onclick="fromMapping()">' + 'Fixed' + '</button>' : ''));
    if (e.as === 'map') {
      if (!e.map || !e.map.items || !e.map.items.length) e.map = savedMap(e.field);   // remembered for this field
      delete e.map.type;
      h += dataMapAttrs(e);
      var mv = ROWS[rowIdx] ? cleanVal(ROWS[rowIdx][e.field]) : '';
      h += '<span class="lbl">Value for this card</span><input class="inp" list="mapValList" value="' + esc(mv) + '" placeholder="' + (ROWS[rowIdx] ? 'Empty — type a value' : 'No cards yet — type to add one') + '" onchange="setRowValue(this.value)">' +
           '<p class="muted" style="margin:.3rem 0 0">Saved in the <b>' + esc(e.field) + '</b> cell for this card.</p>';
    }
    if (e.as === 'image') {
      var ru = ROWS[rowIdx] ? imageUrl(curSrc(e)) : '';
      h += '<span class="lbl">Picture for row ' + (rowIdx + 1) + '</span><div class="img-slot' + (UPLOADING[e.id] ? ' img-loading' : '') + '" id="imgSlot" onclick="openImagePicker(event,\'' + e.id + '\')" title="Click or drop a picture to change it">' +
        (ru ? '<div class="el-img" style="background-image:url(&quot;' + esc(ru) + '&quot;);background-size:contain"></div>' : '<div class="img-slot-empty">' + IMG_ICON + '<span>' + (ROWS[rowIdx] ? 'No picture in this row — drop one, or click to choose' : 'No cards yet — drop a picture to add one') + '</span></div>') +
        '<span class="img-btn">' + IMG_ICON + '</span></div>' +
        '<p class="muted" style="margin:.35rem 0 0">Each row has its own picture, saved in its <b>' + esc(e.field) + '</b> cell. Click or drop a picture here, or double-click it on the card. Step through the rows to set the others.</p>';
    }
    if (e.as !== 'image' && e.as !== 'map') {   // this row's value as typed in the sheet (tags not yet turned into words or pictures) — handy when it's empty on the card
      var rv = ROWS[rowIdx] ? (ROWS[rowIdx][e.field] == null ? '' : String(ROWS[rowIdx][e.field])) : '';
      h += '<span class="lbl">Value for row ' + (rowIdx + 1) + '</span><textarea class="ta" id="a_val"' +
           ' placeholder="' + (ROWS[rowIdx] ? 'Empty in this row — type to fill it in' : 'No cards yet — type to add one') + '" onchange="setRowValue(this.value)">' + esc(rv) + '</textarea>' +
           '<p class="muted" style="margin:.3rem 0 0">Saved in the <b>' + esc(e.field) + '</b> cell for this row.</p>';
    }
    h += dataIsImage(e) ? fitAttrs(e) + boxAttrs(e, 'Frame') : e.as === 'map' ? textAttrs(e) + fitAttrs(e) + boxAttrs(e, 'Background') : textAttrs(e) + boxAttrs(e, 'Background');
  }
  if (e.kind === 'image') {
    var u = imageUrl(picRef(e.src)), src = e.src || '', uploaded = /\/images\/[A-Za-z0-9_\-]+\/[0-9a-f]{20}\.\w+$/.test(src);
    var from = !src ? '' : uploaded ? 'Uploaded file' : /dropbox\.com/i.test(src) ? 'Dropbox link' : /google\.com/i.test(src) ? 'Google Drive link' : 'Web link';
    h += '<span class="lbl">Picture</span><div class="img-slot' + (UPLOADING[e.id] ? ' img-loading' : '') + '" id="imgSlot" onclick="openImagePicker(event,\'' + e.id + '\')" title="Click or drop a picture to change it">' +
         (u ? '<div class="el-img" style="background-image:url(&quot;' + esc(u) + '&quot;);background-size:contain"></div>' : '<div class="img-slot-empty">' + IMG_ICON + '<span>Drop a picture, or click to choose</span></div>') +
         '<span class="img-btn">' + IMG_ICON + '</span></div>';
    if (from) h += '<p class="muted" style="margin:.3rem 0 0">' + from + (uploaded ? '' : ' — loaded from the source, so changes there show up here') + '</p>';
    h += '<p class="muted" style="margin:.3rem 0 0">The same picture on every card — a logo, frame or icon. To pick a picture by each card\'s value, switch to Mapping.</p>';
    h += showSeg('fixed', 'Fixed');
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
      h += '<div class="row2" style="margin-top:.45rem">' + txtInp('Before', 'prefix', e.prefix, '#') + txtInp('After', 'suffix', e.suffix, 'e.g. / {Count}') + '</div>';
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
  h += actionsHTML();
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
function mapEl() { var e = elById(selId); return e && e.map && e.kind === 'data' && e.as === 'map' ? e : null; }
// A data element's mapping: value → text or picture rows, always ending with an empty row for the next one.
// Each row's result is a text box with a picture button; once it has a picture, the picture shows instead.
function dataMapAttrs(e) {
  var mp = e.map, items = mp.items || [], rows = items.concat([{ v: '', t: '', src: '' }]);
  var cur = ROWS[rowIdx] ? cleanVal(ROWS[rowIdx][e.field]).trim().toLowerCase() : null;
  var vals = [];
  ROWS.forEach(function(r) { var v = cleanVal(r[e.field]).trim(); if (v && vals.indexOf(v) < 0 && vals.length < 300) vals.push(v); });
  var h = '<datalist id="mapValList">' + vals.map(function(v) { return '<option value="' + esc(v) + '">'; }).join('') + '</datalist>';
  h += '<span class="lbl">Mappings</span><div class="map-list map-text">';
  rows.forEach(function(it, i) {
    var isNew = i === items.length, on = cur !== null && it.v && it.v.trim().toLowerCase() === cur, u = it.src ? imageUrl(picRef(it.src)) : '';
    var out = it.src
      ? '<div class="map-pic map-slot" data-i="' + i + '" onclick="openImagePicker(event,\'' + e.id + '\',' + i + ')" title="Click or drop to change the picture">' +
          (u ? '<span class="map-thumb" style="background-image:url(&quot;' + esc(u) + '&quot;)"></span>' : '<span class="map-thumb">' + IMG_ICON + '</span>') +
          '<span class="map-pic-name">' + esc(/^\[/.test(it.src) ? it.src : 'Picture') + '</span>' +
          '<button type="button" class="map-pic-x" onclick="event.stopPropagation();setMapSrc(\'' + e.id + '\',' + i + ',\'\')" title="Use text instead" aria-label="Remove the picture">×</button></div>'
      : '<div class="map-out-wrap"><input class="inp map-out" value="' + esc(it.t || '') + '" placeholder="Text…" onchange="setMapText(' + i + ',this.value)">' +
          '<button type="button" class="map-img-btn map-slot" data-i="' + i + '" onclick="openImagePicker(event,\'' + e.id + '\',' + i + ')" title="Show a picture instead (or drop one here)" aria-label="Choose a picture">' + IMG_ICON + '</button></div>';
    h += '<div class="map-trow' + (on ? ' cur' : '') + (isNew ? ' new' : '') + '">' +
      '<input class="inp map-val" list="mapValList" value="' + esc(it.v || '') + '" placeholder="When ' + esc(e.field) + ' is…" onchange="setMapVal(' + i + ',this.value)">' +
      '<span class="map-arrow">→</span>' + out +
      (isNew ? '<span class="map-x-sp"></span>' : '<button type="button" class="map-tx" onclick="delMapItem(' + i + ')" title="Remove this value" aria-label="Remove this value">×</button>') + '</div>';
  });
  h += '</div><p class="muted" style="margin:.35rem 0 0">A card whose <b>' + esc(e.field) + '</b> matches a value shows its text or picture; any other value shows as it is. ' +
       'Double-click the element on the card to change this card’s <b>' + esc(e.field) + '</b>.</p>';
  return '<div class="map-box" style="--n:' + (e.was ? 4 : 3) + '">' + h + '</div>';   // the mappings, kept together on their own background, pointing at Mapping
}
function setMapVal(i, v) {
  var e = mapEl(); if (!e) return; var items = e.map.items; v = v.trim();
  if (i >= items.length) { if (!v) return; snapshot(); items.push({ v: v, src: '' }); }
  else { if (items[i].v === v) return; snapshot(); items[i].v = v; }
  e.map.items = items.filter(function(it) { return it.v || it.src || it.t; });
  changed();
}
function setMapText(i, t) {   // the text a value maps to (a data element mapping to text)
  var e = mapEl(); if (!e) return; var items = e.map.items;
  if (isImageLink(t)) { setMapSrc(e.id, i, cleanVal(t).trim()); return; }   // a pasted picture link becomes the picture
  if (i >= items.length) { if (!t) return; snapshot(); items.push({ v: '', t: t }); }
  else { if ((items[i].t || '') === t) return; snapshot(); items[i].t = t; if (t) delete items[i].src; }
  e.map.items = items.filter(function(it) { return it.v || it.src || it.t; });
  changed();
}
function setMapSrc(id, i, src) {
  var e = elById(id); if (!e || !e.map) return; var items = e.map.items;
  snapshot();
  if (i >= items.length) { if (!src) return; items.push({ v: '', src: src }); } else { items[i].src = src; if (src) delete items[i].t; }
  e.map.items = items.filter(function(it) { return it.v || it.src || it.t; });
  changed(selId !== id); if (selId === id) renderAttrs();
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

// ── Per-tab print / export settings ──────────────────────────────────────────
function savePrintSettings(tab) {
  if (!tab || !layout) return;
  _printSettings[tab] = { paper: $('pPaper').value, orient: $('pOrient').value, gap: $('pGap').value, cut: $('pCut').value, copies: $('pCopies').value, backs: $('pBacks').value };
  try { localStorage.setItem('cb_print:' + SHEET_ID + ':' + tab, JSON.stringify(_printSettings[tab])); } catch (e) {}
}
function restorePrintSettings(tab) {
  var s = _printSettings[tab];
  if (!s) { try { s = JSON.parse(localStorage.getItem('cb_print:' + SHEET_ID + ':' + tab) || 'null'); } catch (e) {} }
  if (!s) return;
  _printSettings[tab] = s;
  if (s.paper) $('pPaper').value = s.paper;
  if (s.orient) $('pOrient').value = s.orient;
  if (s.gap != null) $('pGap').value = s.gap;
  if (s.cut) $('pCut').value = s.cut;
  if (s.backs) $('pBacks').value = s.backs;
  if (s.copies) { $('pCopies').value = s.copies; $('pCopies').dataset.touched = '1'; }
}
function saveExportSettings(tab) {
  if (!tab || !layout) return;
  _exportSettings[tab] = { target: $('eTarget').value, quality: $('eQuality').value, copies: $('eCopies').value };
  try { localStorage.setItem('cb_export:' + SHEET_ID + ':' + tab, JSON.stringify(_exportSettings[tab])); } catch (e) {}
}
function restoreExportSettings(tab) {
  var s = _exportSettings[tab];
  if (!s) { try { s = JSON.parse(localStorage.getItem('cb_export:' + SHEET_ID + ':' + tab) || 'null'); } catch (e) {} }
  if (!s) return;
  _exportSettings[tab] = s;
  if (s.target) $('eTarget').value = s.target;
  if (s.quality) $('eQuality').value = s.quality;
  if (s.copies) { $('eCopies').value = s.copies; $('eCopies').dataset.touched = '1'; }
}

function renderPrint() {
  var pages = $('pages');
  if (!layout) { pages.innerHTML = ''; return; }
  savePrintSettings(curTab);
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
  return { target: $('eTarget').value, quality: +$('eQuality').value || 4096, copies: $('eCopies').value || '', round: false };
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
  saveExportSettings(curTab);
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
// The sheet's picture on the Designs page (/cardboard): its first card. Sent whenever that card looks
// different from the last one sent (after a save, a fetch or on opening), never in the way of anything else.
var _thumbBusy = false;
function keepThumb() {
  if (!isOwner() || !layout || !ROWS.length || !curTab || _rowsPartial || _thumbBusy) return;
  var lay = JSON.parse(JSON.stringify(layout)), row = ROWS[0], c = lay.card, w = 360;
  var html = renderCardHTML(lay, row, false, { card: 0, row: 0, proxy: true });
  var decks = TABS.filter(function(t) { return !t.pending; }).map(function(t) { return t.title; });
  var sig = String(html.length) + ':' + hashStr(html + '|' + decks.join('|') + '|' + SHEET_TITLE), k = 'cb_thumb:' + SHEET_ID;
  try { if (localStorage.getItem(k) === sig) return; } catch (e) {}
  _thumbBusy = true;
  _drawQueue = _drawQueue.then(function() {
    return exportLibs().then(function() { return drawCard(html, w, c); })
      .then(function(cv) {
        var t = document.createElement('canvas'); t.width = cv.width; t.height = cv.height;
        var g = t.getContext('2d'); g.fillStyle = '#eef0f6'; g.fillRect(0, 0, t.width, t.height); g.drawImage(cv, 0, 0);
        return canvasBlob(t, 'image/jpeg');
      })
      .then(function(b) {
        if (!b) return;
        var fd = new FormData(); fd.append('action', 'thumb'); fd.append('id', SHEET_ID); fd.append('file', b, 'thumb.jpg');
        fd.append('title', SHEET_TITLE || ''); fd.append('decks', JSON.stringify(decks));
        if (ACCT && ACCT.token) fd.append('cbt', ACCT.token);
        return fetch(APP_BASE + 'push/cardboardSheets.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
          .then(function(r) { if (r && r.ok) { try { localStorage.setItem(k, sig); } catch (e) {} } });
      });
  }).catch(function() {}).then(function() { _thumbBusy = false; });
}
function hashStr(str) { var h = 5381; for (var i = 0; i < str.length; i++) h = ((h << 5) + h + str.charCodeAt(i)) | 0; return (h >>> 0).toString(36); }
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
  return cacheGet(ck('pub:' + tab)).then(function(c) {
    if (c) { PUBLISHED[tab] = c; if (curTab === tab && _activeTab === 'export') renderLinks(); return; }
    return publishApi('list', { tab: tab }).then(function(r) {
      PUBLISHED[tab] = r.published || {}; savePub(tab);
      if (curTab === tab && _activeTab === 'export') renderLinks();
    });
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
    .then(function(r) { (PUBLISHED[tab] = PUBLISHED[tab] || {})[o.target] = r.published; savePub(tab); return r.published; });
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
    if (PUBLISHED[tab]) delete PUBLISHED[tab][target]; savePub(tab);
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
function setLinkStatus(msg) { var st = $('saveStatus'); if (!st) return; if (msg) { st.dataset.prev = st.textContent; st.textContent = msg; } else if (st.dataset.prev != null) { delete st.dataset.prev; updateSaveBtn(); } }
function copyLink(btn) {
  var inp = btn.previousElementSibling; inp.select();
  (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject()).catch(function() { document.execCommand('copy'); });
  btn.textContent = 'Copied'; setTimeout(function() { btn.textContent = 'Copy'; }, 1400);
}
function renderLinks() {
  var box = $('exportLinks'); if (!box) return;
  var target = $('eTarget').value, m = (PUBLISHED[curTab] || {})[target];
  $('publishGo').innerHTML = m ? 'Update links' : 'Get links';
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
  if (GRID && GRID.tab === tab && !force) { overlayGrid(); renderGrid(); return; }   // already in memory: no need to read the sheet
  box.innerHTML = '<div class="loading"><span class="spin"></span>Loading “' + esc(tab) + '”…</div>';
  api('grid', { tab: tab, force: force ? '1' : '' }).then(function(r) {
    if (curTab !== tab) return;
    GRID = { tab: tab, header: r.header || [], rows: r.rows || [] };
    overlayGrid(); _gridSel = null; renderGrid();
  }).catch(function(e) { box.innerHTML = '<div class="muted" style="padding:2rem">Couldn\'t read the sheet — ' + esc(e.message) + '</div>'; });
}
// Column order and widths in the Data view are kept per tab in _cardboard_settings (the sheet itself is unchanged)
function gridPrefs(tab) { var g = SETTINGS.grid = SETTINGS.grid || {}; return g[tab || GRID.tab] = g[tab || GRID.tab] || {}; }
function gridCols() {
  var cols = GRID.header.map(function(h, i) { return { name: h, i: i }; }).filter(function(c) { return c.name && !isHidden(c.name) && !colDeleted(c.name); });
  var order = gridPrefs().order || [];
  return cols.map(function(c, k) { var o = order.indexOf(c.name); return { c: c, k: o < 0 ? order.length + k : o }; })
    .sort(function(a, b) { return a.k - b.k; }).map(function(x) { return x.c; });
}
// After the last column there's always an empty one, and after the last row an empty row: typing
// into them adds a column (named "Column H" unless you name it in its heading) or a row.
function ghostCol() { var at = GRID.header.length; while (at > 0 && !GRID.header[at - 1]) at--; return at; }
function gridColsAll() { return gridCols().concat([{ name: '', i: ghostCol(), ghost: true }]); }
function rowEmpty(r) { return !r.values.some(function(v) { return String(v || '').trim(); }); }
function ensureTrailingRow() {
  var last = GRID.rows[GRID.rows.length - 1];
  if (!last || !last.draft || !rowEmpty(last)) GRID.rows.push({ row: 0, draft: true, key: uid(), values: new Array(GRID.header.length).fill('') });
}
var _gridAutoW = {};   // a column's starting width, measured once from its contents so it doesn't change while editing
function gridColWidth(name, i) {
  var w = (gridPrefs().w || {})[name]; if (w) return w;
  var key = GRID.tab + '\u0001' + name; if (_gridAutoW[key]) return _gridAutoW[key];
  var cv = gridColWidth._cv || (gridColWidth._cv = document.createElement('canvas').getContext('2d'));
  cv.font = '12.8px system-ui, sans-serif'; var m = cv.measureText(name.toUpperCase()).width * 1.15 + 34;
  cv.font = '12.8px system-ui, sans-serif';
  GRID.rows.slice(0, 200).forEach(function(r) { var v = String(r.values[i] || ''); if (v) m = Math.max(m, cv.measureText(v.slice(0, 80)).width + 22 + (imageUrl(v) && looksLikeImage(name) ? 30 : 0)); });
  return _gridAutoW[key] = Math.round(Math.max(90, Math.min(320, m)));
}
var _gridPrefsTimer = null;
function saveGridPrefs() {
  clearTimeout(_gridPrefsTimer);
  _gridPrefsTimer = setTimeout(function() { if (hasAccess()) markSettings(); }, 300);
}
function gridVisibleRows() {
  var dels = dataPend(GRID.tab).delrows.map(function(d) { return d.row; });
  var rows = GRID.rows.filter(function(r) { return r.draft || dels.indexOf(r.row) < 0; });
  if (!_gridFilter) return rows;
  var f = _gridFilter.toLowerCase();
  return rows.filter(function(r) { return r.draft || r.values.some(function(v) { return String(v).toLowerCase().indexOf(f) >= 0; }); });
}
// What a cell shows: what the sheet displays (formula results, number and date formats), with [tags]
// as their words or pictures (not mappings — an element's own). Double-click a cell to edit what's really in it.
// GRID rows keep both: values (what's in the cell) and shown ({column: [value, displayed]}, from the
// same read). The displayed one is used only while the cell still holds that value — once it's
// edited here, the new value shows (a new formula as written) until the next load after Save.
function gridShown(name, v, res) {
  if (res && res[0] === v) v = res[1];
  else if (/^=/.test(v)) return '<span class="g-formula">' + esc(v) + '</span>';
  // Mappings aren't shown here: the same column can map differently in different elements of the layout
  return rich(textVal(v));
}
function renderGrid() {
  var box = $('gridWrap'); if (!GRID) return;
  ensureTrailingRow();
  var cols = gridColsAll(), rows = gridVisibleRows();
  var nHid = GRID.header.filter(function(hd) { return hd && isHidden(hd); }).length;
  // Only what's worth knowing: how many a search shows, and hidden columns (the Deck box has the card count)
  var info = [];
  if (nHid) info.push(nHid + ' hidden <button type="button" class="lk-act" onclick="showAllFields()">Show all</button>');
  $('gridInfo').innerHTML = info.join(' · ');
  var imgCols = {};
  cols.forEach(function(c) { imgCols[c.i] = FIELDS.indexOf(c.name) >= 0 && looksLikeImage(c.name); });
  var widths = cols.map(function(c) { return c.ghost ? 130 : gridColWidth(c.name, c.i); }), total = 42 + widths.reduce(function(a, b) { return a + b; }, 0);
  var h = '<table class="grid" style="width:' + total + 'px"><colgroup><col style="width:42px">' + widths.map(function(w, k) { return '<col data-c="' + cols[k].i + '" style="width:' + w + 'px">'; }).join('') + '</colgroup>' +
    '<thead><tr><th class="rn"></th>' + cols.map(function(c) {
      if (c.ghost) return '<th class="ghost-col" data-ghost="' + c.i + '" title="Type a name here, or in a cell below, to add a column"></th>';
      return '<th data-c="' + c.i + '" title="Click for options · drag to move · drag the edge to resize"><span class="th-name">' + esc(c.name) + '</span><span class="th-caret">▾</span><span class="col-rs" title="Drag to resize · double-click to fit"></span></th>'; }).join('') + '</tr></thead><tbody>';
  var cardN = {}, nn = 0;   // card numbers, 1 up, in deck order — empty and deleted rows aren't cards
  GRID.rows.forEach(function(x, i) { if (!rowEmpty(x) && !(!x.draft && rowDeleted(x.row))) cardN[i] = ++nn; });
  rows.forEach(function(r) {
    var ri = GRID.rows.indexOf(r);
    h += '<tr data-r="' + ri + '"' + (r.draft ? ' class="draft"' : '') + '><td class="rn" title="Card options">' + (cardN[ri] ? cardNo(cardN[ri]) : '') + '</td>';
    cols.forEach(function(c) {
      var v = r.values[c.i] || '', st = (r.state || {})[c.i] || '';
      var thumb = !c.ghost && imageUrl(v) && (imgCols[c.i] || isImageLink(v)) ? gridThumb(v) : '';
      h += '<td data-r="' + ri + '" data-c="' + c.i + '" class="' + (st ? 'st-' + st : '') + (c.ghost ? ' ghost-col' : '') + (/^=/.test(v) ? ' formula' : '') + '" title="' + esc(v.length > 60 ? v : '') + '">' + thumb + (c.ghost ? '<span>' + esc(v) + '</span>' : gridShown(c.name, v, r.shown && r.shown[c.i])) + '</td>';
    });
    h += '</tr>';
  });
  h += '</tbody></table>';
  if (!rows.length) h += '<div class="muted" style="padding:1rem">No rows' + (_gridFilter ? ' match “' + esc(_gridFilter) + '”' : '') + '.</div>';
  var sx = box.scrollLeft, sy = box.scrollTop;
  box.innerHTML = h; box.scrollLeft = sx; box.scrollTop = sy;
  markFound(box);
  if (_gridSel) markGridSel();
  if (_gridFilter) updateReplaceBtn();
}
function gridCell(r, c) { return $('gridWrap').querySelector('td[data-r="' + r + '"][data-c="' + c + '"]'); }
function markGridSel() {
  var old = $('gridWrap').querySelector('td.sel'); if (old) old.classList.remove('sel');
  var td = _gridSel && gridCell(_gridSel.r, _gridSel.c); if (td) { td.classList.add('sel'); td.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
}
function selectGridCell(r, c) { _gridSel = { r: r, c: c }; markGridSel(); }
function moveGridSel(dr, dc) {
  if (!_gridSel) return;
  var rows = gridVisibleRows().map(function(x) { return GRID.rows.indexOf(x); }), cols = gridColsAll().map(function(x) { return x.i; });
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
// A cell's picture: a placeholder with a spinner straight away, the picture once it has loaded
var BLANK_GIF = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
function gridThumb(v) {   // the placeholder (spinner) shows at once; the picture replaces it when it has loaded
  return '<img class="g-thumb ld" src="' + BLANK_GIF + '" data-src="' + esc(imageUrl(v)) + '" alt="" onload="thumbLoad(this)">';
}
function thumbLoad(img) {
  if (img.dataset.go) return; img.dataset.go = '1';
  var u = img.dataset.src, t = new Image();
  t.referrerPolicy = 'no-referrer';
  t.onload = function() { img.referrerPolicy = 'no-referrer'; img.src = u; img.classList.remove('ld'); };
  t.onerror = function() { img.classList.remove('ld'); img.classList.add('bad'); img.title = 'This picture didn’t load'; };
  t.src = u;
}
function paintCell(r, c) {   // show a cell again after it changed: its picture (for a picture link), tags, mapped value
  var td = gridCell(r, c); if (!td) return;
  var row = GRID.rows[r], v = row.values[c] || '', name = GRID.header[c];
  if (!name) { var sp = td.querySelector('span'); if (sp) sp.textContent = v; else td.innerHTML = '<span>' + esc(v) + '</span>'; }
  else {
    var thumb = imageUrl(v) && (isImageLink(v) || (FIELDS.indexOf(name) >= 0 && looksLikeImage(name))) ? gridThumb(v) : '';
    var res = row.shown && row.shown[c] && row.shown[c][0] === v ? row.shown[c] : null;
    td.innerHTML = thumb + gridShown(name, v, res);
  }
  td.title = v.length > 60 ? v : '';
  td.classList.toggle('formula', /^=/.test(v));
  markFound(td);
  if (_gridFilter) updateReplaceBtn();
}
function saveGridCell(r, c, val, old) {   // kept in this browser until Save
  var row = GRID.rows[r], field = GRID.header[c], tab = GRID.tab;
  if (!field) {   // the empty column at the end: add it to the sheet, then the value
    if (!String(val).trim() || c !== ghostCol()) return;
    var letter = gspreadLetter(c + 1), name = 'Column ' + letter, k = 2;
    while (GRID.header.concat(FIELDS).some(function(f) { return f && f.toLowerCase() === name.toLowerCase(); })) name = 'Column ' + letter + ' ' + (k++);
    row.values[c] = val; paintCell(r, c);
    createColumn(name).then(function() { if (GRID && GRID.tab === tab) saveGridCell(GRID.rows.indexOf(row), c, val, ''); }, function() { row.values[c] = ''; renderGrid(); });
    return;
  }
  row.values[c] = val; paintCell(r, c);
  if (row.draft) {
    var o = {}; GRID.header.forEach(function(h, i) { if (h) o[h] = row.values[i] || ''; });
    row.key = row.key || uid(); queueAdd(tab, row.key, o);
    var card = ROWS.filter(function(x) { return x._pend === row.key; })[0];
    if (!card && Object.keys(o).some(function(k) { return String(o[k]).trim(); })) { card = { _pend: row.key }; ROWS.push(card); }
    if (card) FIELDS.forEach(function(f) { card[f] = o[f] || ''; });
    if (GRID.rows[GRID.rows.length - 1] === row && !rowEmpty(row)) { var sel = _gridSel; renderGrid(); _gridSel = sel; if (sel) markGridSel(); }   // a fresh empty row below
  } else {
    var was = pendingOld(tab, row.row, field);
    queueCell(tab, row.row, field, val, was != null ? was : old);
    var cardRow = ROWS[gridCardIndex(row)]; if (cardRow && !cardRow._pend) cardRow[field] = val;
  }
  if (tab === curTab) { renderData(); if (_activeTab === 'print') renderPrint(); }
}
function gspreadLetter(n) { var s = ''; while (n > 0) { var m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; }
var _addingCol = false;
function createColumn(n) {   // kept in this browser until Save, like every other change
  if (!hasAccess()) { requirePro('edit', function() {}); return Promise.reject(new Error('locked')); }
  var tab = curTab, pd = dataPend(tab);
  if (pd.cols.indexOf(n) < 0) pd.cols.push(n); setDataPend(tab, pd);
  if (FIELDS.indexOf(n) < 0) { FIELDS.push(n); ROWS.forEach(function(row) { row[n] = ''; }); renderData(); }
  gridAddedColumn(n);
  var c = GRID ? GRID.header.indexOf(n) : -1, th = c >= 0 && $('gridWrap').querySelector('th[data-c="' + c + '"]');
  if (th) th.classList.add('just-added');
  return Promise.resolve();
}
function nameNewColumn(th) {
  if (!requirePro('edit', function() {}) || th.classList.contains('renaming')) return;
  th.classList.add('renaming');
  th.innerHTML = '<input class="th-rename" maxlength="60" spellcheck="false" autocomplete="off">';
  var inp = th.querySelector('input'), done = false; inp.focus();
  function finish(save) {
    if (done) return; done = true;
    var n = inp.value.trim().replace(/\s+/g, ' ');
    if (!save || !n) { renderGrid(); return; }
    var bad = isInternal(n) ? '“' + n + '” is used by CardBoard' : /[{}\[\]]/.test(n) ? 'Names can\'t use { } [ or ]'
      : GRID.header.concat(FIELDS).some(function(f) { return f && f.toLowerCase() === n.toLowerCase(); }) ? 'There\'s already a column called “' + n + '”' : '';
    if (bad) { toast(bad, true); renderGrid(); return; }
    createColumn(n).catch(function() { renderGrid(); });
  }
  inp.addEventListener('keydown', function(k) { k.stopPropagation(); if (k.key === 'Enter') { k.preventDefault(); finish(true); } else if (k.key === 'Escape') { k.preventDefault(); finish(false); } });
  inp.addEventListener('blur', function() { finish(true); });
  inp.addEventListener('mousedown', function(k) { k.stopPropagation(); });
  inp.addEventListener('click', function(k) { k.stopPropagation(); });
}
function addGridRow() {
  if (!GRID) return;
  var n = GRID.header.length, r = { row: 0, draft: true, key: uid(), values: new Array(n).fill('') };
  GRID.rows.push(r); $('gridFilter').value = ''; filterGrid('');
  var first = gridCols()[0]; if (first) { selectGridCell(GRID.rows.length - 1, first.i); startGridEdit(); }
}
function clrSync(inp) { var w = inp && inp.closest('.clr-wrap'); if (w) w.classList.toggle('has', !!inp.value); }   // the × shows once something is typed
function filterGrid(v) {
  _gridFilter = v.trim(); renderGrid(); clrSync($('gridFilter')); clrSync($('gridReplaceIn'));
  // Replace slides out once there's something to find (and only for someone who may change the sheet)
  var box = $('gridReplace'), on = !!_gridFilter && hasAccess();
  box.classList.toggle('open', on); box.setAttribute('aria-hidden', on ? 'false' : 'true');
  ['gridReplaceIn', 'gridReplaceBtn'].forEach(function(id) { $(id).tabIndex = on ? 0 : -1; });
  updateReplaceBtn();
}
// The cells Replace would change: found in what's typed in them (not formulas, which are left alone)
function replaceTargets() {
  var f = _gridFilter.toLowerCase(), out = [];
  if (!f || !GRID) return out;
  gridVisibleRows().forEach(function(r) {
    if (r.draft && rowEmpty(r)) return;
    var ri = GRID.rows.indexOf(r);
    GRID.header.forEach(function(name, c) {
      var v = String(r.values[c] || '');
      if (name && !/^=/.test(v) && v.toLowerCase().indexOf(f) >= 0) out.push({ r: ri, c: c, v: v });
    });
  });
  return out;
}
function updateReplaceBtn() {
  var b = $('gridReplaceBtn'); if (!b) return;
  var n = replaceTargets().length;
  b.textContent = n ? 'Replace ' + n : 'Replace all'; b.disabled = !n;
  b.title = n ? 'Replace in ' + n + (n === 1 ? ' cell' : ' cells') + ' — kept in this browser until you save (⌘Z undoes it)' : 'Nothing to replace';
}
function replaceInGrid() {
  if (!requirePro('edit', function() {})) return;
  var find = _gridFilter, by = $('gridReplaceIn').value, list = replaceTargets();
  if (!find || !list.length) return;
  var re = new RegExp(find.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
  list.forEach(function(t) { saveGridCell(t.r, t.c, t.v.replace(re, function() { return by; }), t.v); });   // one undo step: all in one go
  toast('✓ Replaced in ' + list.length + (list.length === 1 ? ' cell' : ' cells'));
  // Keep looking for what was put in, so the changed cells stay in view and marked
  $('gridReplaceIn').value = '';
  if (by.trim()) { $('gridFilter').value = by; filterGrid(by); } else filterGrid(find);
}
// Mark what Find found in the cells as they're shown (text only — not inside pictures or tags' images)
function markFound(root) {
  if (!_gridFilter || !root) return;
  var f = _gridFilter.toLowerCase(), n = f.length;
  var cells = root.tagName === 'TD' ? [root] : Array.prototype.slice.call(root.querySelectorAll('td:not(.rn):not(.ghost-col)'));
  cells.forEach(function(td) {
    // A [tag] shows as its picture: mark the picture when the search is (part of) the tag as typed
    td.querySelectorAll('img.tag-img').forEach(function(im) { im.classList.toggle('g-hit-img', ('[' + (im.alt || '') + ']').toLowerCase().indexOf(f) >= 0); });
    var walker = document.createTreeWalker(td, NodeFilter.SHOW_TEXT), nodes = [], t;
    while ((t = walker.nextNode())) if (!t.parentNode.closest('mark.g-hit') && t.nodeValue.toLowerCase().indexOf(f) >= 0) nodes.push(t);
    nodes.forEach(function(node) {
      var s = node.nodeValue, low = s.toLowerCase(), frag = document.createDocumentFragment(), at = 0, i;
      while ((i = low.indexOf(f, at)) >= 0) {
        if (i > at) frag.appendChild(document.createTextNode(s.slice(at, i)));
        var m = document.createElement('mark'); m.className = 'g-hit'; m.textContent = s.slice(i, i + n); frag.appendChild(m);
        at = i + n;
      }
      if (at < s.length) frag.appendChild(document.createTextNode(s.slice(at)));
      node.parentNode.replaceChild(frag, node);
    });
  });
}
// + Add column (Data tab): a new heading in the sheet, ready to fill in
function gridAddedColumn(n) {   // keep the grid in step when a column is added from either tab
  if (!GRID || GRID.tab !== curTab || GRID.header.indexOf(n) >= 0) return;
  var at = GRID.header.length;
  while (at > 0 && !GRID.header[at - 1]) at--;   // first slot after the last heading
  GRID.header[at] = n; GRID.rows.forEach(function(r) { while (r.values.length < GRID.header.length) r.values.push(''); });
  if (_activeTab === 'data') renderGrid();
}

// Column heading menu: hide the column here and in the Design list
function closeColumnMenu() { var m = $('colMenu'); if (m) m.remove(); document.querySelectorAll('table.grid th.menu-open').forEach(function(t) { t.classList.remove('menu-open'); }); }
function openColumnMenu(th) {
  var open = th.classList.contains('menu-open'); closeColumnMenu(); if (open) return;
  var name = GRID.header[+th.dataset.c]; if (!name) return;
  var used = layout && layout.els.concat(layout.back.els).some(function(e) { return e.field === name || (e.kind === 'text' && (e.text || '').indexOf('{' + name + '}') >= 0); });
  var m = document.createElement('div'); m.id = 'colMenu'; m.className = 'field-menu col-menu';
  m.innerHTML = '<div class="col-menu-name">' + esc(name) + '</div><button type="button" class="cm-rename"><span class="fm-ico">✎</span> Rename</button><button type="button" class="cm-hide"><span class="fm-ico">⊘</span> Hide field</button><button type="button" class="cm-del danger"><span class="fm-ico">🗑</span> Delete column</button>' +
    '<p>Hides the column here and in the Design list. It stays in the sheet' + (used ? ', and still shows on the card.' : '.') + ' “Show all” above brings it back.</p>';
  m.querySelector('.cm-hide').onclick = function() { closeColumnMenu(); setFieldHidden(name, true); toast('Hid “' + name + '”'); };
  m.querySelector('.cm-rename').onclick = function() { closeColumnMenu(); startColumnRename(+th.dataset.c); };
  m.querySelector('.cm-del').onclick = function() { closeColumnMenu(); deleteGridColumn(name); };
  document.body.appendChild(m);
  var r = th.getBoundingClientRect();
  m.style.left = Math.min(r.left, innerWidth - m.offsetWidth - 12) + 'px'; m.style.top = (r.bottom + 4) + 'px';
  th.classList.add('menu-open');
}
document.addEventListener('mousedown', function(ev) { if ($('colMenu') && !ev.target.closest('#colMenu') && !ev.target.closest('th.menu-open')) closeColumnMenu(); });
// Delete a column from the sheet (after asking). Cards that used it show nothing there.
function deleteGridColumn(name) {   // kept in this browser until Save, like every other change
  if (!requirePro('edit', function() {})) return;
  var tab = curTab, pd = dataPend(tab);
  pd.cells = pd.cells.filter(function(x) { return x.field !== name; });
  pd.adds.forEach(function(x) { delete x.values[name]; });
  if (pd.cols.indexOf(name) >= 0) {   // never in the sheet: just forget it
    pd.cols = pd.cols.filter(function(x) { return x !== name; });
    var i = GRID ? GRID.header.indexOf(name) : -1;
    if (i >= 0) { GRID.header.splice(i, 1); GRID.rows.forEach(function(r) { r.values.splice(i, 1); }); }
  } else if (pd.delcols.indexOf(name) < 0) pd.delcols.push(name);
  setDataPend(tab, pd);
  FIELDS = FIELDS.filter(function(f) { return f !== name; });
  ROWS.forEach(function(r) { delete r[name]; });
  var p = (SETTINGS.grid || {})[tab];
  if (p) { if (p.order) p.order = p.order.filter(function(x) { return x !== name; }); if (p.w) delete p.w[name]; }
  if (layout && layout.hidden) layout.hidden = layout.hidden.filter(function(x) { return x !== name; });
  _gridSel = null;
  renderGrid(); renderData(); render();
}
// Click a row's card icon for its menu: a preview of the card, and Delete card
function cardNo(n) { return '<span class="card-no">' + n + '</span>'; }   // a little card with its number
function closeRowMenu() { var m = $('rowMenu'); if (m) m.remove(); document.querySelectorAll('table.grid td.rn.menu-open').forEach(function(t) { t.classList.remove('menu-open'); }); }
function rowPreviewHTML(r, ri) {
  if (!layout) return '';
  var obj = {};
  GRID.header.forEach(function(hd, i) { if (hd) obj[hd] = r.values[i] || ''; });
  var c = layout.card, maxW = 200, s = Math.min(1, maxW / (c.w * PX));
  return '<div class="row-prev" style="width:' + (c.w * PX * s) + 'px;height:' + (c.h * PX * s) + 'px">' +
         '<div style="width:' + c.w + 'pt;height:' + c.h + 'pt;transform:scale(' + s + ');transform-origin:0 0">' +
         renderCardHTML(layout, obj, false, { card: ri, row: ri }) + '</div></div>';
}
function openRowMenu(td) {
  var open = td.classList.contains('menu-open'); closeRowMenu(); closeColumnMenu(); if (open) return;
  var ri = +td.parentNode.dataset.r, r = GRID.rows[ri]; if (!r) return;
  var m = document.createElement('div'); m.id = 'rowMenu'; m.className = 'field-menu col-menu';
  m.innerHTML = rowPreviewHTML(r, ri) + '<button type="button" class="danger"><span class="fm-ico">🗑</span> Delete card</button>';
  m.querySelector('button').onclick = function() { closeRowMenu(); deleteGridRow(ri); };
  document.body.appendChild(m);
  var b = td.getBoundingClientRect();
  m.style.left = (b.right + 4) + 'px'; m.style.top = Math.min(b.top, innerHeight - m.offsetHeight - 12) + 'px';
  td.classList.add('menu-open');
}
document.addEventListener('mousedown', function(ev) { if ($('rowMenu') && !ev.target.closest('#rowMenu') && !ev.target.closest('td.rn.menu-open')) closeRowMenu(); });
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeRowMenu(); });
function moveCardRow(src, before) {   // before: a grid row, or 'end'
  var tab = GRID.tab, list = GRID.rows.filter(function(g) { return !g.draft; }).map(function(g) { return g.row; });
  var from = list.indexOf(src.row); if (from < 0) return;
  list.splice(from, 1);
  var at = before === 'end' ? list.length : list.indexOf(before.row); if (at < 0) at = list.length;
  list.splice(at, 0, src.row);
  var natural = list.slice().sort(function(a, b) { return a - b; });
  var p = dataPend(tab);
  if (list.join() === natural.join()) delete p.order; else p.order = list;
  setDataPend(tab, p);
  var shown = src;   // the card on screen stays the one that moved
  applyRowOrder(tab);
  if (p.order == null) {   // back to the sheet's order
    GRID.rows.sort(function(a, b) { return (a.draft ? 1e9 : a.row) - (b.draft ? 1e9 : b.row); });
    ROWS.sort(function(a, b) { return (a._pend ? 1e9 : a._row || 0) - (b._pend ? 1e9 : b._row || 0); });
  }
  renderGrid(); renderData(); render();
  var ci = gridCardIndex(shown); if (ci >= 0 && tab === curTab) rowIdx = ci;
  if (_activeTab === 'print') renderPrint();
}
function deleteGridRow(ri) {   // kept in this browser until Save
  var r = GRID.rows[ri]; if (!r) return;
  if (!requirePro('edit', function() {})) return;
  var tab = curTab, pd = dataPend(tab);
  if (r.draft) {   // never written to the sheet
    if (r.key) { pd.adds = pd.adds.filter(function(x) { return x.key !== r.key; }); setDataPend(tab, pd); ROWS = ROWS.filter(function(x) { return x._pend !== r.key; }); }
    GRID.rows.splice(ri, 1);
  } else {
    // What the sheet has in this row (not this browser's edits), so Save only deletes it if nobody changed it meanwhile
    var asSheet = r.values.map(function(v, i) {
      var h = GRID.header[i]; if (!h || pd.cols.indexOf(h) >= 0) return h ? '' : v;
      var o = pendingOld(tab, r.row, h); return o != null ? o : v;
    });
    var card = gridCardIndex(r);
    pd.cells = pd.cells.filter(function(x) { return x.row !== r.row; });
    if (!pd.delrows.some(function(d) { return d.row === r.row; })) pd.delrows.push({ row: r.row, values: asSheet });
    setDataPend(tab, pd);
    if (card >= 0 && ROWS[card] && !ROWS[card]._pend) { ROWS.splice(card, 1); ROWS._delDone = true; }
  }
  rowIdx = Math.min(rowIdx, Math.max(0, ROWS.length - 1));
  _gridSel = null; renderGrid(); renderData(); render();
}
// Rename a column: edit its heading (double-click it, or Rename in its menu). The sheet's heading
// changes, and so does everything that uses it: this tab's layout, hidden fields and column widths.
function startColumnRename(c) {
  if (!requirePro('edit', function() {})) return;
  var th = $('gridWrap').querySelector('th[data-c="' + c + '"]'), name = GRID.header[c]; if (!th || !name) return;
  closeColumnMenu();
  th.classList.add('renaming');
  th.innerHTML = '<input class="th-rename" maxlength="60" spellcheck="false" autocomplete="off">';
  var inp = th.querySelector('input'), done = false; inp.value = name; inp.focus(); inp.select();
  function finish(save) {
    if (done) return; done = true;
    var nu = inp.value.trim().replace(/\s+/g, ' ');
    if (!save || !nu || nu === name) { renderGrid(); return; }
    var bad = isInternal(nu) ? '“' + nu + '” is used by CardBoard' : /[{}\[\]]/.test(nu) ? 'Names can\'t use { } [ or ]'
      : FIELDS.concat(GRID.header).some(function(f) { return f && f !== name && f.toLowerCase() === nu.toLowerCase(); }) ? 'There\'s already a column called “' + nu + '”' : '';
    if (bad) { toast(bad, true); renderGrid(); return; }
    renameColumn(name, nu);
  }
  inp.addEventListener('keydown', function(k) { k.stopPropagation(); if (k.key === 'Enter') { k.preventDefault(); finish(true); } else if (k.key === 'Escape') { k.preventDefault(); finish(false); } });
  inp.addEventListener('blur', function() { finish(true); });
  inp.addEventListener('mousedown', function(k) { k.stopPropagation(); });
  inp.addEventListener('click', function(k) { k.stopPropagation(); });
}
// Swap a column name everywhere a layout uses it
function renameInLayout(lay, a, b) {
  if (!lay) return lay;
  [lay, lay.back].forEach(function(f) {
    (f && f.els || []).forEach(function(e) {
      if (e.field === a) e.field = b;
      if (e.kind === 'text' && e.text) e.text = e.text.split('{' + a + '}').join('{' + b + '}');
    });
  });
  if (lay.hidden) lay.hidden = lay.hidden.map(function(x) { return x === a ? b : x; });
  if (lay.maps && lay.maps[a]) { lay.maps[b] = lay.maps[a]; delete lay.maps[a]; }
  return lay;
}
function renameColumn(a, b) {
  var tab = curTab;
  // The column keeps its place and width under its new name straight away (put back if the rename fails)
  var p = (SETTINGS.grid || {})[tab], gridChanged = false, oldPrefs = p ? JSON.stringify(p) : null;
  if (p) {
    if (p.order && p.order.indexOf(a) >= 0) { p.order = p.order.map(function(x) { return x === a ? b : x; }); gridChanged = true; }
    if (p.w && p.w[a] != null) { p.w[b] = p.w[a]; delete p.w[a]; gridChanged = true; }
  }
  var auto = _gridAutoW[tab + '\u0001' + a]; if (auto) _gridAutoW[tab + '\u0001' + b] = auto;
  var c = GRID.header.indexOf(a); if (c >= 0) GRID.header[c] = b; renderGrid();   // show the new name straight away
  var unsaved = pendingCol(tab, a);
  (unsaved ? Promise.resolve() : api('renamefield', { tab: tab, old: a, name: b })).then(function() {
    // the data in memory
    ROWS.forEach(function(r) { if (Object.prototype.hasOwnProperty.call(r, a)) { r[b] = r[a]; delete r[a]; } });
    FIELDS = FIELDS.map(function(f) { return f === a ? b : f; });
    var pd = dataPend(tab);   // unsaved edits in this browser follow the new name
    pd.cells.forEach(function(x) { if (x.field === a) x.field = b; });
    pd.cols = pd.cols.map(function(x) { return x === a ? b : x; });
    pd.adds.forEach(function(x) { if (Object.prototype.hasOwnProperty.call(x.values, a)) { x.values[b] = x.values[a]; delete x.values[a]; } });
    setDataPend(tab, pd);
    // The layout follows the new name: on screen, in the undo history, and kept as this tab's draft in
    // this browser straight away (so reloading keeps it). It goes to the sheet with the next Save — or
    // the quiet save when you pause. (It used to be saved only when nothing else was unsaved, and not at
    // all for a column that wasn't in the sheet yet, so a reload could bring back the old name.)
    var key = 'cb_draft:' + SHEET_ID + ':' + tab, layoutRenamed = false;
    if (curTab === tab && layout) {
      var before = JSON.stringify(layout);
      renameInLayout(layout, a, b);
      if (JSON.stringify(layout) !== before) { layoutRenamed = true; setDirty(true); lsPut(key, JSON.stringify(layout)); }
    } else {   // switched decks while the rename was on its way: update that deck's draft (or saved layout)
      var src = null; try { src = localStorage.getItem(key); } catch (x) {}
      if (!src && LAYOUTS[tab]) src = JSON.stringify(LAYOUTS[tab]);
      if (src) { var nl = JSON.stringify(renameInLayout(JSON.parse(src), a, b)); if (nl !== src) { layoutRenamed = true; lsPut(key, nl); } }
    }
    [undoStack, redoStack].forEach(function(st) { for (var i = 0; i < st.length; i++) st[i] = JSON.stringify(renameInLayout(JSON.parse(st[i]), a, b)); });
    renderData(); render(); renderAttrs(); if (_activeTab === 'data') renderGrid();
    if (gridChanged) markSettings();
    updateSaveBtn();
    dataUndoClear(tab);   // the sheet has the new name now: earlier steps don't fit it
    toast('✓ Renamed “' + a + '” to “' + b + '”' + (layoutRenamed ? ' — the layout uses the new name' : ''));
    if (!unsaved) autoRepublish(tab);
  }).catch(function(e) {
    var k = GRID.header.indexOf(b); if (k >= 0 && FIELDS.indexOf(b) < 0) GRID.header[k] = a;
    if (p && oldPrefs && FIELDS.indexOf(b) < 0) SETTINGS.grid[tab] = JSON.parse(oldPrefs);
    renderGrid(); toast('Couldn\'t rename — ' + e.message, true);
  });
}
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeColumnMenu(); });
$('gridWrap').addEventListener('scroll', function() { closeColumnMenu(); closeRowMenu(); });

// Rows changed here: re-read them for the card designer when leaving the Data tab
function refreshRows() {
  var tab = curTab; _rowsStale = false;
  return api('rows', { tab: tab, force: '1' }).then(function(res) {
    if (curTab !== tab) return;
    ROWS = (res.rows || []).filter(function(r) { return Object.keys(r).some(function(k) { return !isInternal(k) && String(r[k]).trim(); }); });
    FIELDS = [];
    ROWS.forEach(function(r) { Object.keys(r).forEach(function(k) { if (!isInternal(k) && FIELDS.indexOf(k) < 0) FIELDS.push(k); }); });
    overlayPending();
    if (!FIELDS.length || FIELDS.length === dataPend(tab).cols.length) headerFields(tab);
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
    if (_colDragged) { _colDragged = false; return; }
    var rn = ev.target.closest('tbody td.rn'); if (rn) { var rr = GRID.rows[+rn.parentNode.dataset.r]; if (rr && !(rr.draft && rowEmpty(rr))) openRowMenu(rn); return; }
    var gh = ev.target.closest('th.ghost-col'); if (gh) { nameNewColumn(gh); return; }
    if (ev.target.classList.contains('col-rs')) return;
    var th = ev.target.closest('th[data-c]'); if (th) openColumnMenu(th);
  });
  // Resize: drag a heading's right edge. Double-click the edge to fit its contents again.
  wrap.addEventListener('dblclick', function(ev) {
    var hd = ev.target.closest('th[data-c]');
    if (hd && !ev.target.classList.contains('col-rs') && !ev.target.closest('.th-rename')) { startColumnRename(+hd.dataset.c); return; }
    if (!ev.target.classList.contains('col-rs')) return;
    var name = GRID.header[+ev.target.closest('th').dataset.c], w = gridPrefs().w;
    if (w && w[name]) { delete w[name]; saveGridPrefs(); } renderGrid();
  });
  var _colDragged = false;
  wrap.addEventListener('mousedown', function(ev) {
    var th = ev.target.closest('th[data-c]'); if (!th || ev.button !== 0 || th.classList.contains('renaming')) return;
    var c = +th.dataset.c, name = GRID.header[c], table = wrap.querySelector('table.grid'), col = table.querySelector('col[data-c="' + c + '"]');
    var x0 = ev.clientX, moved = false;
    if (ev.target.classList.contains('col-rs')) {
      ev.preventDefault(); closeColumnMenu();
      var w0 = th.getBoundingClientRect().width, t0 = table.getBoundingClientRect().width;
      document.body.classList.add('col-resizing');
      var mv = function(e) { var w = Math.max(50, Math.round(w0 + e.clientX - x0)); col.style.width = w + 'px'; table.style.width = (t0 - w0 + w) + 'px'; moved = true; };
      var up = function() {
        document.removeEventListener('mousemove', mv); document.removeEventListener('mouseup', up); document.body.classList.remove('col-resizing');
        if (!moved) return;
        var p = gridPrefs(); (p.w = p.w || {})[name] = parseInt(col.style.width, 10); saveGridPrefs(); _colDragged = true;
        setTimeout(function() { _colDragged = false; }, 0);
      };
      document.addEventListener('mousemove', mv); document.addEventListener('mouseup', up);
      return;
    }
    // Move: drag a heading sideways and drop it where the marker shows
    var marker = null, ghost = null, target = -1, ths = [].slice.call(table.querySelectorAll('thead th[data-c]'));
    var mv2 = function(e) {
      if (!moved && Math.abs(e.clientX - x0) < 6) return;
      if (!moved) {
        moved = true; closeColumnMenu(); document.body.classList.add('col-moving'); th.classList.add('col-src');
        ghost = document.createElement('div'); ghost.className = 'col-ghost'; ghost.textContent = name; document.body.appendChild(ghost);
        marker = document.createElement('div'); marker.className = 'col-marker'; wrap.appendChild(marker);
      }
      ghost.style.left = (e.clientX + 10) + 'px'; ghost.style.top = (e.clientY + 8) + 'px';
      var wr = wrap.getBoundingClientRect(), best = 0, bx = 0, bd = 1e9;
      for (var k = 0; k <= ths.length; k++) {
        var r = ths[Math.min(k, ths.length - 1)].getBoundingClientRect(), x = k < ths.length ? r.left : r.right, d = Math.abs(e.clientX - x);
        if (d < bd) { bd = d; best = k; bx = x; }
      }
      target = best;
      marker.style.left = (bx - wr.left + wrap.scrollLeft - 1) + 'px'; marker.style.top = wrap.scrollTop + 'px'; marker.style.height = Math.min(table.offsetHeight, wrap.clientHeight) + 'px';
      if (e.clientX > wr.right - 30) wrap.scrollLeft += 12; else if (e.clientX < wr.left + 60) wrap.scrollLeft -= 12;
    };
    var up2 = function() {
      document.removeEventListener('mousemove', mv2); document.removeEventListener('mouseup', up2);
      if (!moved) return;
      document.body.classList.remove('col-moving'); th.classList.remove('col-src'); ghost.remove(); marker.remove();
      _colDragged = true; setTimeout(function() { _colDragged = false; }, 0);
      var names = gridCols().map(function(x) { return x.name; }), from = names.indexOf(name);
      if (target < 0 || target === from || target === from + 1) return;
      names.splice(from, 1); names.splice(target > from ? target - 1 : target, 0, name);
      var p = gridPrefs(), all = (p.order || []).filter(function(n) { return names.indexOf(n) < 0 && GRID.header.indexOf(n) >= 0; });
      p.order = names.concat(all);   // hidden columns keep a place at the end
      renderGrid(); saveGridPrefs();
    };
    document.addEventListener('mousemove', mv2); document.addEventListener('mouseup', up2);
  });
  // Move a card: drag its number up or down and drop it where the line shows. The card moves in the
  // sheet when you save (kept in this browser until then, like every edit); one undo step.
  wrap.addEventListener('mousedown', function(ev) {
    var rn = ev.target.closest('tbody td.rn'); if (!rn || ev.button !== 0) return;
    var ri = +rn.parentNode.dataset.r, src = GRID.rows[ri]; if (!src || src.draft) return;
    var y0 = ev.clientY, moved = false, marker = null, ghost = null, target = null, table = wrap.querySelector('table.grid');
    var trs = [].slice.call(table.querySelectorAll('tbody tr')).filter(function(tr) { var g = GRID.rows[+tr.dataset.r]; return g && !g.draft; });
    var mv = function(e) {
      if (!moved && Math.abs(e.clientY - y0) < 6) return;
      if (!moved) {
        if (!hasAccess()) { up(); requirePro('edit', function() {}); return; }
        moved = true; closeRowMenu(); document.body.classList.add('row-moving'); rn.parentNode.classList.add('row-src');
        ghost = document.createElement('div'); ghost.className = 'col-ghost';
        ghost.textContent = (src.values.filter(function(v) { return String(v).trim(); })[0] || 'Card').slice(0, 40); document.body.appendChild(ghost);
        marker = document.createElement('div'); marker.className = 'row-marker'; wrap.appendChild(marker);
      }
      ghost.style.left = (e.clientX + 12) + 'px'; ghost.style.top = (e.clientY + 8) + 'px';
      var wr = wrap.getBoundingClientRect(), best = 0, by = 0, bd = 1e9;
      for (var k = 0; k <= trs.length; k++) {
        var r = trs[Math.min(k, trs.length - 1)].getBoundingClientRect(), y = k < trs.length ? r.top : r.bottom, d = Math.abs(e.clientY - y);
        if (d < bd) { bd = d; best = k; by = y; }
      }
      target = best < trs.length ? GRID.rows[+trs[best].dataset.r] : 'end';
      marker.style.top = (by - wr.top + wrap.scrollTop - 1) + 'px'; marker.style.left = wrap.scrollLeft + 'px'; marker.style.width = Math.min(table.offsetWidth, wrap.clientWidth) + 'px';
      if (e.clientY > wr.bottom - 30) wrap.scrollTop += 12; else if (e.clientY < wr.top + 60) wrap.scrollTop -= 12;
    };
    var up = function() {
      document.removeEventListener('mousemove', mv); document.removeEventListener('mouseup', up);
      if (!moved) return;
      document.body.classList.remove('row-moving'); rn.parentNode.classList.remove('row-src'); ghost.remove(); marker.remove();
      _colDragged = true; setTimeout(function() { _colDragged = false; }, 0);
      if (!target || target === src) return;
      moveCardRow(src, target);
    };
    document.addEventListener('mousemove', mv); document.addEventListener('mouseup', up);
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
      var rec = recentColors().filter(function(c) { return /^#[0-9a-f]{6}$/i.test(c); }), sw = (rec.length ? rec : PALETTE.slice(0, 12)).slice(0, 12);
      h += '<div class="icon-color"><span class="mini">Color</span><input type="color" value="' + esc(color.length === 7 ? color : '#2e2763') + '" data-color title="Any color">' +
        '<div class="icon-recent" title="' + (rec.length ? 'Recent colors' : 'Colors') + '">' + sw.map(function(c) { return '<button type="button" class="cp-tile' + (c.toLowerCase() === String(color).toLowerCase() ? ' on' : '') + '" style="--c:' + c + '" data-pc="' + c + '" title="' + c + '"></button>'; }).join('') + '</div></div>' +
        '<div class="icon-grid' + (isLight(color) ? ' dark' : '') + '">' +
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
    if (cin) { cin.oninput = function() { setIconColor(cin.value); }; cin.onchange = function() { rememberColor(cin.value); }; }
    var u = ov.querySelector('[data-url]'); if (u) setTimeout(function() { u.focus(); }, 30);
  }
  // the icons in the chosen color; light colors on a dark background so they show
  function setIconColor(c) {
    color = c;
    ov.querySelectorAll('.icon-cell img').forEach(function(im) { im.src = iconUri(im.alt, color); });
    var g = ov.querySelector('.icon-grid'); if (g) g.classList.toggle('dark', isLight(color));
    ov.querySelectorAll('.icon-recent [data-pc]').forEach(function(b) { b.classList.toggle('on', b.dataset.pc.toLowerCase() === color.toLowerCase()); });
    var ci = ov.querySelector('[data-color]'); if (ci && ci.value.toLowerCase() !== color.toLowerCase()) ci.value = color;
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
    var pc = e.target.closest('[data-pc]'); if (pc) { setIconColor(pc.dataset.pc); return; }
    var ic = e.target.closest('[data-icon]'); if (ic) { rememberColor(color); done('icon:' + ic.dataset.icon + ':' + color); return; }
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
      (o.alt ? '<button type="button" class="attr-btn" data-a="alt">' + esc(o.alt) + '</button>' : '') +
      '<button type="button" class="print-go' + (o.danger ? ' danger' : '') + '" data-a="1">' + esc(o.ok || 'OK') + '</button></div></div>';
    var prev = document.activeElement;
    function close(v) { document.removeEventListener('keydown', key, true); ov.remove(); if (prev && prev.focus) prev.focus(); done(v); }
    function key(ev) {
      if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); close(false); }
      else if (ev.key === 'Enter') { ev.preventDefault(); ev.stopPropagation(); var f = document.activeElement; close(f && f.dataset && f.dataset.a ? (f.dataset.a === 'alt' ? 'alt' : f.dataset.a === '1') : !o.danger); }
      else if (ev.key === 'Tab') {   // keep focus inside the dialog
        var b = ov.querySelectorAll('button'), i = Array.prototype.indexOf.call(b, document.activeElement);
        ev.preventDefault(); b[(i + (ev.shiftKey ? b.length - 1 : 1)) % b.length].focus();
      }
    }
    ov.addEventListener('click', function(ev) {
      var b = ev.target.closest('[data-a]');
      if (b) close(b.dataset.a === 'alt' ? 'alt' : b.dataset.a === '1'); else if (ev.target === ov) close(false);
    });
    document.addEventListener('keydown', key, true);
    document.body.appendChild(ov);
    ov.querySelector(o.danger ? '[data-a="0"]' : '[data-a="1"]').focus();   // risky actions default to Cancel
  });
}

// ── Menu: Profile, Settings, Fetch, Subscribe, Help ──────────────────────────
// Profile and settings live in the spreadsheet's "layouts" tab, on their own row.
// _cardboard_settings keeps this spreadsheet's settings only (units). The profile is the account's.
var SETTINGS_KEY = '_cardboard_settings', SETTINGS = { units: 'in' }, SHEET_TITLE = '', _oldProfile = null;
function openDialog(id) { $(id).classList.add('open'); }
function closeDialog(id) { $(id).classList.remove('open'); }
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(function(o) { o.classList.remove('open'); }); });
function saveSettings() {
  var keep = Object.assign({}, SETTINGS); delete keep.profile;
  return api('save', { tab: SETTINGS_KEY, layout: JSON.stringify(keep) }).then(function() { LAYOUTS[SETTINGS_KEY] = keep; _oldProfile = null; writeStartCache(); });
}

// Profile: kept with the person's GameBoard account (their row in the Admin sheet). The email is
// the one they sign in with; changing it means signing in with the other address.
function openProfileDialog() {
  if (!ACCT) { askSignIn().then(openProfileDialog, function() {}); return; }
  var a = acctInfo(), old = _oldProfile || {};   // older versions kept a profile in _cardboard_settings
  $('pfName').value = a.name || old.name || ''; $('pfEmail').value = a.email || ''; $('pfStudio').value = a.studio || old.studio || '';
  $('pfErr').textContent = ''; $('pfSave').disabled = false; $('pfSave').textContent = 'Save';
  openDialog('profileOverlay'); setTimeout(function() { $('pfName').focus(); }, 60);
}
function saveProfile() {
  var btn = $('pfSave'); btn.disabled = true; btn.textContent = 'Saving…';
  accountPost('profile', { name: $('pfName').value.trim(), studio: $('pfStudio').value.trim() })
    .then(function(r) {
      storeAcct(r); closeDialog('profileOverlay'); toast('✓ Profile saved');
      if (_oldProfile && hasAccess()) saveSettings().catch(function() {});   // take the old copy out of the sheet
    })
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
  markSettings();
}
function unitName() { return SETTINGS.units === 'mm' ? 'mm' : 'in'; }
function unitStep() { return SETTINGS.units === 'mm' ? '0.5' : '0.05'; }
function toUnit(pt) { return SETTINGS.units === 'mm' ? Math.round(pt / 72 * 25.4 * 10) / 10 : Math.round(pt / 72 * 100) / 100; }
function fromUnit(v) { v = parseFloat(v); return SETTINGS.units === 'mm' ? v / 25.4 * 72 : v * 72; }

// ── Account: a free week with every feature, then $4.99/month ────────────────
// People sign in with a code sent to their email. The account (and its signed token) is kept
// in this browser, so the app knows the plan straight away; it's checked with the server
// (the GameBoard Admin sheet) every few hours, and right after subscribing.
var ACCT = null, ACCT_KEY = 'cb_account', ACCT_MAX_AGE = 4 * 3600 * 1000;
try { ACCT = JSON.parse(localStorage.getItem(ACCT_KEY) || 'null'); } catch (e) { ACCT = null; }
function acctInfo() { return (ACCT && ACCT.account) || null; }
function storeAcct(res) {
  ACCT = { token: res.token, account: res.account, sheet: SHEET_ID, checked: Date.now() };
  try { localStorage.setItem(ACCT_KEY, JSON.stringify(ACCT)); } catch (e) {}
  applyAcct();
}
function forgetAcct() { ACCT = null; try { localStorage.removeItem(ACCT_KEY); } catch (e) {} applyAcct(); }
// The sheet's owner — the account that set it up (its Admin-sheet Sheets list) — changes it; everyone
// else sees it read-only. Until the account has been checked for this sheet, it's read-only too.
function acctChecked() { return !!(ACCT && ACCT.sheet === SHEET_ID && (!ACCT.app || ACCT.app === 'cardboard')); }   // the other apps keep the same account, checked for themselves
function isOwner() { var a = acctInfo(); return !!a && acctChecked() && a.owner === true; }   // an account checked before owners existed isn't one until checked again
function viewOnly() { return !!acctInfo() && !isOwner(); }
function hasAccess() {
  var a = acctInfo(); if (!a) return false;
  if (!isOwner()) return false;
  if (a.sheet_unlocked && ACCT.sheet === SHEET_ID) return true;
  return a.state !== 'locked' && new Date(a.until).getTime() > Date.now();
}
function daysLeft() { var a = acctInfo(); return a ? Math.max(0, Math.ceil((new Date(a.until).getTime() - Date.now()) / 86400000)) : 0; }
function accountPost(action, extra) {
  var fd = new FormData(); fd.append('action', action); fd.append('sheet', SHEET_ID);
  Object.keys(extra || {}).forEach(function(k) { fd.append(k, extra[k]); });
  if (ACCT && ACCT.token) fd.append('cbt', ACCT.token);
  return fetch(APP_BASE + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
    .then(function(r) { if (!r || r.error) { var e = new Error(r && r.error ? r.error : 'No response'); e.needAuth = !!(r && r.need_auth); throw e; } return r; });
}
var _acctRefreshing = null;
function claimSheet() {   // a sheet nobody has set up: it becomes this account's
  accountPost('claim').then(function(r) { storeAcct(r); toast(isOwner() ? '✓ This sheet is yours now' : 'Someone else has set this sheet up', !isOwner()); })
    .catch(function(e) { toast('Couldn’t make it yours — ' + e.message, true); });
}
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
    $('lockBtn').hidden = false;
    if (!a && _guest) { $('lockMsg').innerHTML = '<b>Read only.</b> You’re viewing this deck without signing in — sign in to design your own cards (first week free).'; $('lockBtn').textContent = 'Sign in'; $('lockBtn').onclick = function() { askSignIn().then(function() { location.reload(); }, function() {}); }; }
    else if (!a) { $('lockMsg').innerHTML = '<b>Sign in to start designing</b> — your first week is free, with every feature.'; $('lockBtn').textContent = 'Sign in'; $('lockBtn').onclick = function() { askSignIn().catch(function() {}); }; }
    else if (!acctChecked()) { $('lockMsg').innerHTML = 'Checking your account…'; $('lockBtn').hidden = true; }
    else if (!isOwner() && a.unclaimed) { $('lockMsg').innerHTML = '<b>View only.</b> Nobody has set this sheet up in CardBoard yet — if it’s yours, make it yours to design its cards.'; $('lockBtn').textContent = 'Make it mine'; $('lockBtn').onclick = claimSheet; }
    else if (!isOwner()) { $('lockMsg').innerHTML = '<b>View only.</b> This sheet was set up by another GameBoard account — you can look through its cards and layouts, but not change them.'; $('lockBtn').hidden = true; }
    else { $('lockMsg').innerHTML = '<b>Your free week has ended.</b> Your cards and decks are safe — subscribe for $4.99/month to keep designing.'; $('lockBtn').textContent = 'Subscribe'; $('lockBtn').onclick = function() { openPlansDialog('locked'); }; }
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
  if (a.state === 'code') return '<b>Unlocked with a code</b> <span class="muted">· until ' + esc(fmtWhen(a.until)) + '</span>';
  if (a.state === 'trial') { var d = daysLeft(); return '<b>Free week</b> <span class="muted">· ' + d + (d === 1 ? ' day' : ' days') + ' left</span>'; }
  return '<b>Free week ended</b>';
}
function fmtWhen(v) {
  var d = /^\d+$/.test(String(v)) ? new Date(+v * (+v < 1e12 ? 1000 : 1)) : new Date(v);
  return isNaN(d) ? String(v) : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
function updateSubMenu() {
  var mp = $('menuPic'), im = (acctInfo() || {}).image || '';   // the profile picture in place of the menu icon
  if (mp) { mp.hidden = !im; if (im && mp.getAttribute('src') !== im) mp.src = im; $('menuIco').style.display = im ? 'none' : ''; }
  var b = $('cbSubBtn'); if (!b) return;
  var a = acctInfo(), st = a && (a.sheet_unlocked && ACCT.sheet === SHEET_ID ? 'activated' : a.state);
  b.textContent = !a ? 'Sign in' : 'Subscription';
  b.title = a ? planLabel().replace(/<[^>]+>/g, '') : '';
  b.style.color = st === 'subscribed' || st === 'activated' || st === 'code' ? '#4caf50' : '#f5a623';
  var so = $('cbSignOut'); if (so) so.hidden = !a;
  var cb = $('cbCodeBtn'); if (cb) cb.hidden = !a || a.state === 'activated';   // the person's own account, not a comped sheet
}
// Returns true when allowed; otherwise asks to sign in or shows the plans
function requirePro(reason, retry) {
  if (hasAccess()) return true;
  if (viewOnly()) { toast(acctChecked() ? 'View only — this sheet belongs to another GameBoard account' : 'Checking your account…', true); return false; }
  if (!ACCT) askSignIn().then(function() { if (hasAccess()) retry(); }, function() {});
  else openPlansDialog('locked');
  return false;
}
var LOCK_REASONS = { locked: 'Your free week has ended. Subscribe to keep designing — your layouts and decks are just as you left them.' };
function openPlansDialog(reason) {
  if (!ACCT) { askSignIn().then(function() { openPlansDialog(reason); }, function() {}); return; }
  $('planIntro').textContent = LOCK_REASONS[reason] || 'Every feature, for a week free. Then $4.99 a month to keep designing.';
  $('planActKey').innerHTML = ''; openDialog('plansOverlay'); renderPlans();
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
  renderCodeBox($('planActKey'), a.state === 'activated');
}
// Account menu → Enter a code: the plans dialog with the code box already open
function openCodeDialog() {
  openPlansDialog();
  var link = document.querySelector('#planActKey .zs-ak-link'); if (link) link.click();
}
// "Have a code?": a free code from the Admin sheet's Codes tab (a week, a month, a year…), or an activation key
function renderCodeBox(el, unlocked) {
  if (!el) return;
  if (unlocked) { el.innerHTML = '<div class="zs-ak"><span class="zs-ak-ok">✓ Unlocked with an activation key</span></div>'; return; }
  if (el.querySelector('form')) return;   // keep a code box that is open (a fresh dialog clears it)
  el.innerHTML = '<div class="zs-ak"><button type="button" class="zs-ak-link">Have a code?</button></div>';
  el.querySelector('.zs-ak-link').onclick = function() {
    var box = el.querySelector('.zs-ak');
    box.innerHTML = '<label class="zs-ak-lbl">Code</label><form class="zs-ak-row"><input type="text" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Code"><button type="submit">Use code</button></form><div class="zs-ak-msg" role="status"></div>';
    var form = box.querySelector('form'), inp = box.querySelector('input'), btn = box.querySelector('button'), msg = box.querySelector('.zs-ak-msg');
    inp.focus(); inp.addEventListener('keydown', function(k) { k.stopPropagation(); });
    form.onsubmit = function(e) {
      e.preventDefault();
      var code = inp.value.trim(); if (!code) { inp.focus(); return; }
      btn.disabled = true; btn.textContent = 'Checking…'; msg.textContent = '';
      accountPost('redeem', { code: code }).then(function(r) {
        storeAcct(r); el.innerHTML = ''; renderPlans(); refreshCards();
        var forCb = !r.apps || !r.apps.length || r.apps.indexOf('CardBoard') >= 0;   // a code can be for other apps only
        toast(!forCb ? '✓ Code added for ' + r.apps.join(', ') : r.days > 0 ? '✓ Unlocked until ' + fmtWhen(r.account.until) : '✓ CardBoard unlocked for good');
      }).catch(function(err) { msg.textContent = err.message; btn.disabled = false; btn.textContent = 'Use code'; inp.select(); });
    };
  };
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
  askDialog({ title: 'Sign out?', message: 'You can sign back in any time with your email and password.', ok: 'Sign out', cancel: 'Cancel' })
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
  $('siEmail').value = (acctInfo() && acctInfo().email) || '';
  signInStep('');
  openDialog('signinOverlay');
  return d.promise;
}
function closeSignIn() {
  if (document.body.classList.contains('cb-gate')) { window.location.href = APP_BASE + 'cardboard'; return; }
  closeDialog('signinOverlay'); if (_signIn) { var d = _signIn; _signIn = null; d.rej(new Error('Not signed in')); }
}
// A shared link opened without an account: look through the cards, read-only
var _guest = false;
function viewAsGuest() {
  _guest = true; document.body.classList.remove('cb-gate'); closeDialog('signinOverlay');
  if (_signIn) { var d = _signIn; _signIn = null; d.rej(new Error('Viewing read only')); }
  init();
}
function signInChoose(mode) { signInStep(mode === 'trial' ? 'signup' : mode); }
function signInStep(mode) {   // '' = the two choices
  _siMode = mode; _siPending = null;
  $('siChoose').hidden = !!mode; $('siStep1').hidden = !mode; $('siStep2').hidden = true; $('siErr').textContent = '';
  $('siTitle').textContent = { login: 'Log in', signup: 'Start your free week', reset: 'Reset your password' }[mode] || 'Welcome to CardBoard';
  $('siSub').textContent = { login: 'Use your GameBoard email and password.',
    signup: 'Every feature for 7 days — no card needed. Choose a password; we’ll email you a code to confirm.',
    reset: 'Choose a new password; we’ll email you a code to confirm it’s you.' }[mode] || 'Design and print cards from your Google Sheet.';
  $('siPass').value = ''; $('siPass2').value = '';
  $('siPass').placeholder = mode === 'login' ? 'Password' : 'Password (8 or more characters)';
  $('siPass').autocomplete = mode === 'login' ? 'current-password' : 'new-password';
  $('siPass2').hidden = $('siRules').hidden = mode === 'login'; checkPwRules();
  $('siForgot').hidden = mode !== 'login';
  $('siSend').disabled = false; $('siSend').textContent = { login: 'Log in', signup: 'Create account', reset: 'Email me a code' }[mode] || '';
  $('siSwitch').textContent = mode === 'login' ? 'New here? Start your free week' : mode ? 'Already have an account? Log in' : '';
  if (mode) setTimeout(function() { ($('siEmail').value ? $('siPass') : $('siEmail')).focus(); }, 40);
}
// New password: tick off each rule as it's met (the server checks the same rules)
function checkPwRules() {
  var pw = $('siPass').value, all = true, ok = {
    len: pw.length >= 8, letter: /\p{L}/u.test(pw), num: /\d/.test(pw), match: pw !== '' && pw === $('siPass2').value };
  $('siRules').querySelectorAll('li').forEach(function(li) { var y = ok[li.dataset.rule]; li.classList.toggle('ok', y); if (!y) all = false; });
  return all;
}
document.addEventListener('input', function(ev) { if ((ev.target.id === 'siPass' || ev.target.id === 'siPass2') && checkPwRules()) $('siErr').textContent = ''; });
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
  else if (!checkPwRules()) { $('siErr').textContent = 'Your password needs everything on the list.'; return; }
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
  accountPost('verify', { email: _siPending.email, password: _siPending.password, code: code }).then(function(r) { _siPending = null; signedIn(r); })
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
