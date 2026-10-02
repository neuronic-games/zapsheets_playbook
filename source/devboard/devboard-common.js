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
      d = new Date(raw);
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
        current.obs.push({ obs:obs, sol:sol });
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
}

function _swParseRounds(str) {
  var m = (str || '').match(/Rounds:\s*(\d+)/);
  return m ? parseInt(m[1], 10) : 0;
}

// ── Stopwatch ─────────────────────────────────────────────────────────────────
var _swSeconds  = 0;
var _swRunning  = false;
var _swInterval = null;

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
    clearInterval(_swInterval);
    _swInterval = null;
    _swRunning = false;
    if (btn) btn.classList.remove('sw-running');
  } else {
    _swRunning = true;
    if (btn) btn.classList.add('sw-running');
    _swInterval = setInterval(function() {
      _swSeconds++;
      _swUpdate();
    }, 1000);
  }
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
    clearInterval(_swInterval);
    _swInterval = null;
    _swRunning = false;
    var btn = document.getElementById('swBtn');
    if (btn) btn.classList.remove('sw-running');
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
  var btn = document.getElementById('swBtn');
  if (btn) btn.classList.remove('sw-running');
  _swUpdate();
  _sessionRounds = 0;
  var rc = document.getElementById('roundsCount');
  if (rc) rc.textContent = '0';
}
