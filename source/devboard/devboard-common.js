// devboard-common.js — shared utilities for dashboard and collab views

// ── Dialog shake (dirty-close feedback) ──────────────────────────────────────
function shakeDialog(el) {
  if (!el) return;
  el.classList.remove('dialog-shake');
  void el.offsetWidth;  // reflow to restart animation
  el.classList.add('dialog-shake');
  el.addEventListener('animationend', function handler() {
    el.classList.remove('dialog-shake');
    el.removeEventListener('animationend', handler);
  });
}

// ── HTML escaping ─────────────────────────────────────────────────────────────
function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Date formatting ───────────────────────────────────────────────────────────
function fmtDate(raw) {
  if (raw === null || raw === undefined || raw === '') return '';
  var d;
  // Google Sheets serial date (integer or numeric string with no dashes/slashes)
  var n = Number(raw);
  if (!isNaN(n) && n > 1000 && String(raw).trim().match(/^\d+$/)) {
    // Sheets epoch: Dec 30, 1899
    d = new Date(Date.UTC(1899, 11, 30) + n * 86400000);
  } else {
    // ISO "YYYY-MM-DD" — parse as UTC to avoid timezone day-shift
    var iso = String(raw).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
      var p = iso.split('-');
      d = new Date(Date.UTC(+p[0], +p[1]-1, +p[2]));
    } else {
      // "11/30/2026" and the like are read as local midnight: keep that day (getUTC… below would shift it east of UTC)
      d = new Date(raw);
      if (!isNaN(d.getTime())) d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    }
  }
  if (!d || isNaN(d.getTime())) return String(raw);
  var mm = String(d.getUTCMonth() + 1).padStart(2, '0');
  var dd = String(d.getUTCDate()).padStart(2, '0');
  var yyyy = d.getUTCFullYear();
  return mm + '/' + dd + '/' + yyyy;
}

// ── Render plain text with bullet hanging-indent and newline support ──────────
// Each line becomes a <span class="obs-line"> block; bullet lines additionally
// get class "obs-bul" which CSS styles with a hanging indent.
function bulletsHtml(text) {
  var lines = String(text || '').split('\n');
  return lines.map(function(line) {
    if (line.startsWith('• ')) {
      return '<span class="obs-bul">' + esc(line) + '</span>';
    }
    return '<span class="obs-line">' + (esc(line) || '<br>') + '</span>';
  }).join('');
}

// ── Render observation text (pass =IMAGE() formulas through as <img>) ─────────
function obsHtml(text) {
  var parts = String(text || '').split(/(=IMAGE\("[^"]*"\))/i);
  return parts.map(function(p) {
    var m = p.match(/^=IMAGE\("([^"]*)"\)$/i);
    if (m) return '<img src="' + esc(m[1]) + '" style="max-width:100%;max-height:220px;border-radius:4px;display:block;margin-top:.25rem">';
    return bulletsHtml(p);
  }).join('');
}

// ── Copy a session's notes: formatted (for email or Google Docs) and as plain text ──
var _copySess = {};
// The Copy button in a session's header (shown while the session is open); key: unique on the page
function sessCopyBtn(key, game, s) {
  _copySess[key] = { g: game, s: s };
  return '<button type="button" class="sess-copy-btn" title="Copy these notes, formatted, to paste into an email or a doc"' +
    ' onclick="event.stopPropagation();copySessionNotes(\'' + esc(String(key).replace(/'/g, '')) + '\',this)">' +
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copy</button>';
}
function _sessCellHtml(text) {   // lines, bullets and pictures, with inline styles that survive pasting
  return String(text || '').split(/(=IMAGE\("[^"]*"\))/i).map(function(p) {
    var m = p.match(/^=IMAGE\("([^"]*)"\)$/i);
    if (m) return '<img src="' + esc(m[1]) + '" alt="" style="max-width:320px;max-height:240px;display:block;margin:4px 0">';
    return p.split('\n').map(function(l) { return esc(l); }).join('<br>');
  }).join('');
}
function _sessCellText(text) {
  return String(text || '').replace(/=IMAGE\("([^"]*)"\)/gi, '[picture: $1]').trim();
}
function sessionNotesFormatted(game, s) {
  var title = (game ? game + ' — ' : '') + (s.testnum || 'Session');
  var meta = [s.date ? fmtDate(s.date) : '', s.location || '', s.length || ''].filter(Boolean).join(' · ');
  var who = (s.testers || []).join(', ');
  var F = 'font-family:Arial,Helvetica,sans-serif;';
  var th = F + 'text-align:left;font-size:13px;padding:6px 10px;border:1px solid #c9d6df;background:#eef4f7;color:#1a5f7a';
  var td = F + 'font-size:13px;padding:6px 10px;border:1px solid #c9d6df;vertical-align:top';
  var h = '<div style="' + F + 'color:#222">' +
    '<h3 style="' + F + 'font-size:16px;margin:0 0 4px">' + esc(title) + '</h3>' +
    (meta ? '<p style="' + F + 'font-size:13px;color:#555;margin:0 0 2px">' + esc(meta) + '</p>' : '') +
    (who ? '<p style="' + F + 'font-size:13px;color:#555;margin:0 0 2px">Testers: ' + esc(who) + '</p>' : '');
  var t = [title];
  if (meta) t.push(meta);
  if (who) t.push('Testers: ' + who);
  var obs = s.obs || [];
  if (obs.length) {
    h += '<table style="border-collapse:collapse;width:100%;margin-top:8px"><thead><tr><th style="' + th + ';width:50%">Observations</th><th style="' + th + '">Solutions</th></tr></thead><tbody>' +
      obs.map(function(o) { return '<tr><td style="' + td + '">' + _sessCellHtml(o.obs) + '</td><td style="' + td + ';color:#1a5f7a">' + _sessCellHtml(o.sol) + '</td></tr>'; }).join('') +
      '</tbody></table>';
    t.push('');
    obs.forEach(function(o) {
      t.push('• ' + _sessCellText(o.obs).replace(/\n/g, '\n  '));
      if (_sessCellText(o.sol)) t.push('  → ' + _sessCellText(o.sol).replace(/\n/g, '\n    '));
    });
  } else {
    h += '<p style="' + F + 'font-size:13px;color:#888;margin:8px 0 0">No notes.</p>';
  }
  return { html: h + '</div>', text: t.join('\n') };
}
function copySessionNotes(key, btn) {
  var c = _copySess[key]; if (!c) return;
  var out = sessionNotesFormatted(c.g, c.s);
  var done = function(ok) {
    if (!btn) return;
    var was = btn.getAttribute('data-label') || btn.innerHTML; btn.setAttribute('data-label', was);
    btn.innerHTML = ok ? '✓ Copied' : 'Couldn’t copy';
    btn.classList.toggle('done', !!ok);
    clearTimeout(btn._t); btn._t = setTimeout(function() { btn.innerHTML = was; btn.classList.remove('done'); }, 1800);
  };
  var fallback = function() {   // select a formatted copy on the page and copy that
    var d = document.createElement('div');
    d.contentEditable = 'true'; d.innerHTML = out.html;
    d.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
    document.body.appendChild(d);
    var r = document.createRange(); r.selectNodeContents(d);
    var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
    var ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
    sel.removeAllRanges(); document.body.removeChild(d);
    done(ok);
  };
  if (navigator.clipboard && window.ClipboardItem) {
    navigator.clipboard.write([new ClipboardItem({
      'text/html':  new Blob([out.html], { type: 'text/html' }),
      'text/plain': new Blob([out.text], { type: 'text/plain' })
    })]).then(function() { done(true); }, fallback);
  } else fallback();
}
(function() {
  var st = document.createElement('style');
  // In the header, centred top to bottom, in place of the note count (the page sets --sess-right so both line up with its + T&M button)
  st.textContent = '.session-header{position:relative}.session-header .sess-copy-btn{display:none;position:absolute;top:50%;right:calc(var(--sess-right,2.6rem) + 1rem);transform:translateY(-50%)}' +
    '.session-block.open > .session-header .sess-copy-btn{display:inline-flex}' +
    '.session-block.open > .session-header:has(.sess-copy-btn) .session-count{visibility:hidden}' +
    '.sess-copy-btn{font-family:DINBlack,sans-serif;font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;display:inline-flex;align-items:center;gap:.3rem;' +
    'background:transparent;color:#1a5f7a;border:1.5px solid #1a5f7a;border-radius:6px;padding:.25rem .6rem;cursor:pointer;transition:background .15s,color .15s}' +
    '.sess-copy-btn:hover{background:#1a5f7a;color:#fff}.sess-copy-btn.done{background:#2e7a52;border-color:#2e7a52;color:#fff}';
  (document.head || document.documentElement).appendChild(st);
})();

// ── Bullet hanging-indent CSS variable (set after fonts load) ────────────────
function _setBulIndent() {
  var sp = document.createElement('span');
  sp.style.cssText = 'font-family:DINRegular,Arial,sans-serif;font-size:.85rem;' +
    'visibility:hidden;position:fixed;top:-9999px;white-space:nowrap;';
  sp.textContent = '• ';
  document.body.appendChild(sp);
  var emBase = parseFloat(getComputedStyle(document.documentElement).fontSize) * 0.85;
  var w = sp.getBoundingClientRect().width;
  document.body.removeChild(sp);
  if (emBase > 0) {
    document.documentElement.style.setProperty('--bul-indent', (w / emBase).toFixed(4) + 'em');
  }
}
if (document.fonts && document.fonts.ready) {
  document.fonts.ready.then(function() { _setBulIndent(); });
} else {
  _setBulIndent();
}

// ── Build session objects from flat sheet rows ────────────────────────────────
// Row types:  header (date+event set)  |  tester (People col)  |  obs (obs/sol cols)
function buildSessions(rows) {
  if (!rows || !rows.length) return [];
  var sessions = [], current = null;
  rows.forEach(function(row) {
    var date   = (row['Date']         || '').trim();
    var event  = (row['Event']        || '').trim();
    var people = (row['People']       || '').trim();
    var obs    = (row['Observations'] || row['Observation'] || '').trim();
    var sol    = (row['Solutions']    || row['Thoughts']    || row['Solution']    || '').trim();
    if (date || event) {
      // New schema: Event = type ("Playtest"), People = session number ("1")
      // Old schema: Event = "Playtest 1", People = blank — both work transparently
      var sessionLabel = event + (people ? ' ' + people : '');
      current = { date:date, testnum:sessionLabel, eventType:event, sessionNum:people, location:obs, length:sol, testers:[], obs:[] };
      sessions.push(current);
    } else if (current) {
      if (people) {
        // Submitter attribution row: [sub:Name]
        if (people.charAt(0) === '[' && people.slice(0, 5) === '[sub:' && people.charAt(people.length - 1) === ']') {
          current.submittedBy = people.slice(5, -1);
        } else {
          // Strip email suffix stored alongside name (legacy data)
          var tname = people.replace(/\s+\S+@\S+\.\S+\s*$/, '').trim() || people.trim();
          current.testers.push(tname);
        }
      } else if (obs || sol) {
        // TODO list: a "TODO:<id>" note on the row (id links it to the todos sheet; plain "TODO" has none yet)
        var notes = row['_notes'] || {}, todo = '', todoId = '';
        Object.keys(notes).forEach(function(k) {
          var m = String(notes[k]).match(/^\s*TODO\s*(?::\s*([A-Za-z0-9]+))?\s*$/i);
          if (m && !todo) { todo = 'TODO'; todoId = (m[1] || '').toUpperCase(); }
        });
        current.obs.push({ obs:obs, sol:sol, todo:todo, todoId:todoId, row:row['_row'] || 0 });
      }
    }
  });
  return sessions;
}

// ── Session search matching ───────────────────────────────────────────────────
function _sessionMatchesQuery(s, q) {
  if (!q) return true;
  if ((s.testnum  || '').toLowerCase().indexOf(q) !== -1) return true;
  if ((s.location || '').toLowerCase().indexOf(q) !== -1) return true;
  if ((s.date     || '').toLowerCase().indexOf(q) !== -1) return true;
  for (var t = 0; t < s.testers.length; t++) {
    if (s.testers[t].toLowerCase().indexOf(q) !== -1) return true;
  }
  for (var o = 0; o < s.obs.length; o++) {
    if ((s.obs[o].obs || '').toLowerCase().indexOf(q) !== -1) return true;
    if ((s.obs[o].sol || '').toLowerCase().indexOf(q) !== -1) return true;
  }
  return false;
}

// ── Filter obs rows for display when a search query is active ─────────────────
// Returns the subset of obs rows matching q, or all rows if none match
// (session matched via header info — date, tester, location — so show all notes).
function _filterObsByQuery(obs, q) {
  if (!q) return obs;
  var matched = obs.filter(function(o) {
    return (o.obs || '').toLowerCase().indexOf(q) !== -1 ||
           (o.sol || '').toLowerCase().indexOf(q) !== -1;
  });
  return matched.length ? matched : obs;
}

// ── Rounds counter ────────────────────────────────────────────────────────────
var _sessionRounds = 0;

function _roundsChange(delta) {
  _sessionRounds = Math.max(0, _sessionRounds + delta);
  var el = document.getElementById('roundsCount');
  if (el) el.textContent = _sessionRounds;
  _swPersist();
}

function _swParseRounds(str) {
  var m = (str || '').match(/Rounds:\s*(\d+)/);
  return m ? parseInt(m[1], 10) : 0;
}

// ── Stopwatch ─────────────────────────────────────────────────────────────────
// The timer belongs to one session (_swKey) and is saved in this browser
// (localStorage) as a start time, so it keeps running while the dialog is
// closed and across page reloads. Only the timer button stops it.
var _swSeconds   = 0;
var _swRunning   = false;
var _swInterval  = null;
var _swKey       = null;   // which session the timer belongs to (null = not saved)
var _swBase      = 0;      // seconds accumulated before the current run
var _swStartedAt = null;   // ms timestamp of the current run (null = paused)
var _SW_STORE    = 'devboard_timers:' + location.pathname;

function _swStoreRead() {
  try { return JSON.parse(localStorage.getItem(_SW_STORE) || '{}') || {}; } catch (e) { return {}; }
}
function _swStoreWrite(map) {
  try { localStorage.setItem(_SW_STORE, JSON.stringify(map)); } catch (e) {}
}
function _swPersist() {
  if (!_swKey) return;
  var map = _swStoreRead();
  map[_swKey] = { base: _swBase, startedAt: _swStartedAt, rounds: _sessionRounds };
  _swStoreWrite(map);
}
function _swForget(key) {
  if (!key) return;
  var map = _swStoreRead();
  if (map[key]) { delete map[key]; _swStoreWrite(map); }
}
function _swTick() {
  _swSeconds = _swBase + (_swStartedAt ? Math.max(0, Math.floor((Date.now() - _swStartedAt) / 1000)) : 0);
  _swUpdate();
}
function _swStartInterval() {
  clearInterval(_swInterval);
  _swInterval = setInterval(_swTick, 1000);
}

// Attach the timer to a session. A saved timer for that session (running or
// paused) is restored; otherwise it starts paused at fallbackSecs / fallbackRounds.
function _swLoad(key, fallbackSecs, fallbackRounds) {
  _swKey = key || null;
  var saved = _swKey ? _swStoreRead()[_swKey] : null;
  clearInterval(_swInterval); _swInterval = null;
  if (saved) {
    _swBase = saved.base || 0; _swStartedAt = saved.startedAt || null;
    _sessionRounds = saved.rounds != null ? saved.rounds : (fallbackRounds || 0);
  } else {
    _swBase = fallbackSecs || 0; _swStartedAt = null; _sessionRounds = fallbackRounds || 0;
  }
  _swRunning = !!_swStartedAt;
  var btn = document.getElementById('swBtn');
  if (btn) btn.classList.toggle('sw-running', _swRunning);
  if (_swRunning) _swStartInterval();
  var rc = document.getElementById('roundsCount');
  if (rc) rc.textContent = String(_sessionRounds);
  _swTick();
}

// After a session is saved: a running timer moves to the saved session's key
// (so it keeps going there); a paused one is forgotten (its time is in the sheet).
function _swAfterSave(newKey) {
  var oldKey = _swKey;
  if (_swRunning && newKey) { _swForget(oldKey); _swKey = newKey; _swPersist(); }
  else { _swForget(oldKey); _swKey = null; }
}

function _swFormat(secs) {
  if (_swRunning) {
    var m = Math.floor(secs / 60);
    var s = secs % 60;
    return m + ':' + String(s).padStart(2, '0');
  }
  return String(Math.floor(secs / 60));
}

function _swUpdate() {
  var timeStr = _swFormat(_swSeconds);
  var swTime = document.getElementById('swTime');
  if (swTime) swTime.textContent = timeStr;
  var icon = document.querySelector('#swBtn svg');
  if (icon) icon.style.display = _swRunning ? 'none' : '';
}

// Parse "Length: N" (minutes), "Length: MM:SS", or "Length: H:MM:SS" → total seconds
function _swParseLength(str) {
  str = (str || '').trim();
  var m = str.match(/Length:\s*(\d+):(\d+):(\d+)/);
  if (m) return parseInt(m[1]) * 3600 + parseInt(m[2]) * 60 + parseInt(m[3]);
  m = str.match(/Length:\s*(\d+):(\d+)/);
  if (m) return parseInt(m[1]) * 60 + parseInt(m[2]);
  m = str.match(/Length:\s*(\d+)/);
  if (m) return parseInt(m[1]) * 60;
  return 0;
}

var _swLongPressTimer = null;

function toggleStopwatch() {
  var btn = document.getElementById('swBtn');
  if (_swRunning) {
    _swTick();
    _swBase = _swSeconds; _swStartedAt = null;
    clearInterval(_swInterval);
    _swInterval = null;
    _swRunning = false;
    if (btn) btn.classList.remove('sw-running');
  } else {
    _swBase = _swSeconds; _swStartedAt = Date.now();
    _swRunning = true;
    if (btn) btn.classList.add('sw-running');
    _swStartInterval();
  }
  _swPersist();
  _swUpdate();
}

function _swStartLongPress() {
  _swLongPressTimer = setTimeout(function() {
    _swLongPressTimer = null;
    _swExpandOpen();
  }, 2000);
}

function _swExpandOpen() {
  if (_swRunning) {
    _swTick();
    _swBase = _swSeconds; _swStartedAt = null;
    clearInterval(_swInterval);
    _swInterval = null;
    _swRunning = false;
    var btn = document.getElementById('swBtn');
    if (btn) btn.classList.remove('sw-running');
    _swPersist();
  }
  document.getElementById('swWrap').classList.add('sw-expanded');
  var inp = document.getElementById('swPanelInput');
  var mins = Math.floor(_swSeconds / 60);
  inp.value = mins > 0 ? String(mins) : '';
  inp.focus();
  inp.select();
}

function _swExpandCommit() {
  var val = parseInt(document.getElementById('swPanelInput').value, 10);
  _swSeconds = isNaN(val) ? 0 : Math.max(0, val) * 60;
  _swBase = _swSeconds; _swStartedAt = null;
  _swPersist();
  document.getElementById('swWrap').classList.remove('sw-expanded');
  _swUpdate();
}

function _swExpandCancel() {
  document.getElementById('swWrap').classList.remove('sw-expanded');
}

function _swCancelLongPress(e) {
  if (_swLongPressTimer) {
    clearTimeout(_swLongPressTimer);
    _swLongPressTimer = null;
  } else {
    e.preventDefault();
    e.stopPropagation();
  }
}

function _swReset() {
  clearInterval(_swInterval);
  _swInterval = null;
  _swRunning  = false;
  _swSeconds  = 0;
  _swBase = 0; _swStartedAt = null; _swKey = null;
  var btn = document.getElementById('swBtn');
  if (btn) btn.classList.remove('sw-running');
  _swUpdate();
  _sessionRounds = 0;
  var rc = document.getElementById('roundsCount');
  if (rc) rc.textContent = '0';
}
