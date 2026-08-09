#!/usr/bin/env python3
"""
gfetchnotes.py — refresh local notes cache from Google Sheets.

For every "[{game}] notes" tab (and the plain "notes" tab) in the spreadsheet:
  1. Ensures the topic is in noteboard-index.json (adds it if missing).
  2. Reads all submitted notes and writes notes-{safe}-en.json.

Usage:
  python3 gfetchnotes.py <sheet_id>            # refresh all topics
  python3 gfetchnotes.py <sheet_id> <game>     # refresh one topic, return notes

Returns JSON to stdout:
  {"ok": true, "logs": [...]}                        # all-topics mode
  {"ok": true, "notes": [...], "logs": [...]}        # single-topic mode
  {"error": "...", "logs": [...]}
"""

import sys
import os
import json
import re
import hashlib

# ── locate credentials ────────────────────────────────────────────────────────
_here     = os.path.dirname(os.path.abspath(__file__))
cred_path = os.path.join(_here, '..', 'credentials.json')

logs = []

def log(msg, t='info'):
    logs.append({'msg': msg, 'type': t})

def fail(msg):
    print(json.dumps({'error': msg, 'ok': False, 'logs': logs}))
    sys.exit(1)

# ── parse args ────────────────────────────────────────────────────────────────
sheet_id   = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
single_game = (sys.argv[2] if len(sys.argv) > 2 else '').strip()  # optional

if not sheet_id:
    fail('sheet_id argument required')

# ── authenticate ──────────────────────────────────────────────────────────────
if not os.path.exists(cred_path):
    fail('credentials.json not found')

try:
    import gspread
    sa = gspread.service_account(filename=cred_path)
except ImportError:
    fail('gspread not installed — run: pip install gspread')
except Exception as e:
    fail(f'Auth failed: {e}')

# ── open spreadsheet ──────────────────────────────────────────────────────────
log('Connecting to spreadsheet…')
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    fail(f'Could not open spreadsheet: {e}')
log('Connected.', 'ok')

# ── find relevant tabs ────────────────────────────────────────────────────────
try:
    all_ws = wb.worksheets()
except Exception as e:
    fail(f'Could not list worksheets: {e}')

ws_by_title = {ws.title: ws for ws in all_ws}

if single_game:
    # Single-game mode: find just this tab
    if single_game.lower() == 'notes':
        tab_ws = ws_by_title.get('notes') or next(
            (ws for ws in all_ws if ws.title.lower() == 'notes'), None)
    else:
        tab_name = f'[{single_game}] notes'
        tab_ws = ws_by_title.get(tab_name)

    if not tab_ws:
        fail(f'Tab not found for "{single_game}"')

    notes_tabs  = []
    default_tab = None
    total       = 1
else:
    # All-topics mode
    notes_tabs  = [ws for ws in all_ws if re.match(r'^\[.+\] notes$', ws.title)]
    default_tab = next((ws for ws in all_ws if ws.title.lower() == 'notes'), None)
    total       = len(notes_tabs) + (1 if default_tab else 0)

    if not notes_tabs and not default_tab:
        log('No notes tabs found.', 'info')
        print(json.dumps({'ok': True, 'logs': logs}))
        sys.exit(0)

    log(f'Found {total} notes tab(s).', 'info')

# ── output directory ──────────────────────────────────────────────────────────
out_dir    = os.path.join(_here, '..', 'sheets', sheet_id)
index_path = os.path.join(out_dir, 'noteboard-index.json')
os.makedirs(out_dir, exist_ok=True)

# ── load (or init) noteboard-index.json ──────────────────────────────────────
if os.path.exists(index_path):
    try:
        with open(index_path, 'r', encoding='utf-8') as f:
            nb_index = json.load(f)
    except Exception:
        nb_index = {}
else:
    nb_index = {}
    log('noteboard-index.json not found locally — will rebuild.', 'info')

index_dirty = False

def _assign_hash(name):
    for h, n in nb_index.items():
        if n == name:
            return h, False
    h = hashlib.md5(name.encode('utf-8')).hexdigest()[:12]
    i = 12
    while h in nb_index and i <= 32:
        i += 1
        h = hashlib.md5(name.encode('utf-8')).hexdigest()[:i]
    return h, True

def _read_tab(ws, fallback_topic):
    """Read a notes worksheet. Returns (topic_display_name, notes_list)."""
    rows = ws.get_all_values()
    # Detect 2-row structure: row 0 = ["Name", topic], row 1 = headers
    if rows and rows[0] and rows[0][0] == 'Name' and (len(rows[0]) < 2 or rows[0][1] != 'Name'):
        topic   = rows[0][1] if len(rows[0]) > 1 and rows[0][1] else fallback_topic
        data_rows = rows[2:]
    else:
        topic     = fallback_topic
        data_rows = rows[1:]
    notes = []
    for row in data_rows:
        while len(row) < 4:
            row.append('')
        d, name, email, note = row[0], row[1], row[2], row[3]
        if note.strip():
            notes.append({'date': d, 'name': name, 'email': email, 'note': note})
    return topic, notes

def _fetch_tab(ws, topic_name):
    """Read tab, update index, write cache. Returns (topic, notes, ok)."""
    global index_dirty, count
    safe_name = re.sub(r'[/\\]', '-', topic_name)
    game_hash, is_new = _assign_hash(topic_name)
    if is_new:
        nb_index[game_hash] = topic_name
        index_dirty = True
        log(f'Registered "{topic_name}" → {game_hash}.', 'ok')
    log(f'Fetching "{topic_name}"…')
    try:
        topic, notes = _read_tab(ws, topic_name)
        cache_path   = os.path.join(out_dir, f'notes-{safe_name}-en.json')
        with open(cache_path, 'w', encoding='utf-8') as f:
            json.dump({'topic': topic, 'notes': notes}, f, ensure_ascii=False, indent=2)
        log(f'  {len(notes)} note(s) saved.', 'ok')
        count += 1
        return topic, notes, True
    except Exception as e:
        log(f'  Error reading "{topic_name}": {e}', 'error')
        return topic_name, [], False

# ── process ───────────────────────────────────────────────────────────────────
count = 0

if single_game:
    fetched_topic, fetched_notes, _ = _fetch_tab(tab_ws, single_game)
else:
    if default_tab:
        _fetch_tab(default_tab, 'notes')
    for ws in notes_tabs:
        m = re.match(r'^\[(.+)\] notes$', ws.title)
        if m:
            _fetch_tab(ws, m.group(1))  # noqa: discard return value in bulk mode

# ── persist updated index ─────────────────────────────────────────────────────
if index_dirty:
    try:
        with open(index_path, 'w', encoding='utf-8') as f:
            json.dump(nb_index, f, ensure_ascii=False)
        log('Updated noteboard-index.json.', 'ok')
    except Exception as e:
        log(f'Warning: could not write noteboard-index.json: {e}', 'error')

if single_game:
    log('Done.', 'ok')
    print(json.dumps({'ok': True, 'topic': fetched_topic, 'notes': fetched_notes, 'logs': logs}))
else:
    log(f'Done — {count} of {total} tab(s) updated.', 'ok')
    print(json.dumps({'ok': True, 'logs': logs}))
