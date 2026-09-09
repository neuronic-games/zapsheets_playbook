// devboard-common.js — shared utilities for dashboard and collab views

// ── HTML escaping ─────────────────────────────────────────────────────────────
function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Date formatting ───────────────────────────────────────────────────────────
function fmtDate(raw) {
  if (!raw) return '';
  var d = new Date(raw);
  if (isNaN(d.getTime())) return raw;
  return d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
}

// ── Render observation text (pass =IMAGE() formulas through as <img>) ─────────
function obsHtml(text) {
  var parts = String(text || '').split(/(=IMAGE\("[^"]*"\))/i);
  return parts.map(function(p) {
    var m = p.match(/^=IMAGE\("([^"]*)"\)$/i);
    if (m) return '<img src="' + esc(m[1]) + '" style="max-width:100%;max-height:220px;border-radius:4px;display:block;margin-top:.25rem">';
    return esc(p);
  }).join('');
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
    var sol    = (row['Thoughts']     || row['Solution']    || '').trim();
    if (date || event) {
      current = { date:date, testnum:event, location:obs, testers:[], obs:[] };
      sessions.push(current);
    } else if (current) {
      if (people) {
        // Strip email suffix stored alongside name (legacy data)
        var tname = people.replace(/\s+\S+@\S+\.\S+\s*$/, '').trim() || people.trim();
        current.testers.push(tname);
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
