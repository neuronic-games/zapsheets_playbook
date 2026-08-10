#!/usr/bin/env python3
"""
gfetchpulseboard.py — refresh PulseBoard machine cache from Google Sheets.

Re-reads all machine group tabs and updates the local JSON cache files.
Tabs that no longer exist in the spreadsheet are removed from the index
and their cache files are deleted.

Arg:    "{sheet_id}"
Returns: {"ok": true, "logs": [...]} or {"error": "...", "logs": [...]}
"""

import sys, os, json, re

_here     = os.path.dirname(os.path.abspath(__file__))
cred_path = os.path.join(_here, '..', 'credentials.json')

logs = []
def log(msg, t='info'): logs.append({'msg': msg, 'type': t})
def fail(msg):
    print(json.dumps({'ok': False, 'error': msg, 'logs': logs}))
    sys.exit(1)

sheet_id = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
if not sheet_id: fail('sheet_id argument required')

if not os.path.exists(cred_path): fail('credentials.json not found')

try:
    import gspread
    sa = gspread.service_account(filename=cred_path)
except ImportError:
    fail('gspread not installed')
except Exception as e:
    fail(f'Auth failed: {e}')

log('Connecting to spreadsheet…')
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    fail(f'Could not open spreadsheet: {e}')
log('Connected.', 'ok')

SKIP_TITLES = {'settings', 'notes', 'games'}

out_dir  = os.path.join(_here, '..', 'sheets', sheet_id)
idx_path = os.path.join(out_dir, 'pulseboard-index.json')
os.makedirs(out_dir, exist_ok=True)

all_ws      = wb.worksheets()
machine_tabs = [ws for ws in all_ws if ws.title.lower() not in SKIP_TITLES
                and not re.match(r'^\[.+\] notes$', ws.title)]

existing_safes = {re.sub(r'[/\\]', '-', ws.title) for ws in machine_tabs}

# Load existing index
index = {}
if os.path.exists(idx_path):
    try:
        index = json.load(open(idx_path, encoding='utf-8')) or {}
    except Exception:
        index = {}

# Remove stale entries
for safe in list(index.keys()):
    if safe not in existing_safes:
        cache_file = os.path.join(out_dir, f'pulseboard-{safe}.json')
        if os.path.exists(cache_file):
            os.remove(cache_file)
        del index[safe]
        log(f'Removed stale tab "{safe}".', 'info')

COL_MAP = {
    'exhibit':       'exhibit',
    'host':          'host',
    'ip':            'ip',
    'os':            'os',
    'memory':        'memory',
    'disk':          'disk',
    'uptime':        'uptime',
    'last reboot':   'last_reboot',
    'status':        'status',
    'time':          'time',
    'crashes':       'crashes',
    'crash times':   'crash_times',
    'storage':       'storage',
    'startup time':  'startup_time',
    'shutdown time': 'shutdown_time',
    'tv id':         'tv_id',
    'tv password':   'tv_password',
    'notes':         'notes',
}

def _read_machines(ws):
    rows = ws.get_all_values()
    if not rows: return []
    header = [c.strip().lower() for c in rows[0]]
    col_idx = {COL_MAP[h]: i for i, h in enumerate(header) if h in COL_MAP}
    machines = []
    for row in rows[1:]:
        exhibit = row[col_idx['exhibit']].strip() if 'exhibit' in col_idx and col_idx['exhibit'] < len(row) else ''
        if not exhibit: continue
        m = {key: (row[idx].strip() if idx < len(row) else '') for key, idx in col_idx.items()}
        machines.append(m)
    return machines

count = 0
for ws in machine_tabs:
    safe = re.sub(r'[/\\]', '-', ws.title)
    log(f'Fetching "{ws.title}"…')
    try:
        machines  = _read_machines(ws)
        cache     = {'tab': ws.title, 'machines': machines}
        cache_path = os.path.join(out_dir, f'pulseboard-{safe}.json')
        with open(cache_path, 'w', encoding='utf-8') as f:
            json.dump(cache, f, ensure_ascii=False, indent=2)
        index[safe] = ws.title
        count += 1
        log(f'  {len(machines)} machine(s) updated.', 'ok')
    except Exception as e:
        log(f'  Error: {e}', 'error')

with open(idx_path, 'w', encoding='utf-8') as f:
    json.dump(index, f, ensure_ascii=False)

log(f'Done — {count} tab(s) updated.', 'ok')
print(json.dumps({'ok': True, 'logs': logs}))
