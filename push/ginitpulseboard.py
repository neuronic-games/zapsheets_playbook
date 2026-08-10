#!/usr/bin/env python3
"""
ginitpulseboard.py — initialise PulseBoard for a Google Spreadsheet.

Reads every worksheet (skipping Settings/notes tabs), treats each as a
machine group, caches its rows, and writes pulseboard-index.json.

Arg:    "{sheet_id}"
Returns: {"ok": true, "tabs": [...], "logs": [...]}
      or {"error": "...", "logs": [...]}
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
if not sheet_id:
    fail('sheet_id argument required')

if not os.path.exists(cred_path):
    fail('credentials.json not found')

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

# Tabs to skip
SKIP_TITLES = {'settings', 'notes', 'games'}

out_dir = os.path.join(_here, '..', 'sheets', sheet_id)
os.makedirs(out_dir, exist_ok=True)

all_ws = wb.worksheets()
machine_tabs = [ws for ws in all_ws if ws.title.lower() not in SKIP_TITLES
                and not re.match(r'^\[.+\] notes$', ws.title)]

if not machine_tabs:
    fail('No machine group tabs found in this spreadsheet.')

log(f'Found {len(machine_tabs)} machine group tab(s).', 'info')

# Column name → dict key mapping (case-insensitive header match)
COL_MAP = {
    'exhibit':       'exhibit',
    'host':          'host',
    'ip':            'ip',
    'status':        'status',
    'memory':        'memory',
    'storage':       'storage',
    'time':          'time',
    'startup time':  'startup_time',
    'shutdown time': 'shutdown_time',
    'crashes':       'crashes',
    'crash times':   'crash_times',
    'tv id':         'tv_id',
    'tv password':   'tv_password',
    'os':            'os',
    'notes':         'notes',
}

def _read_machines(ws):
    rows = ws.get_all_values()
    if not rows:
        return []
    # Build column index from header row
    header = [c.strip().lower() for c in rows[0]]
    col_idx = {}
    for i, h in enumerate(header):
        if h in COL_MAP:
            col_idx[COL_MAP[h]] = i
    machines = []
    for row in rows[1:]:
        exhibit = row[col_idx['exhibit']].strip() if 'exhibit' in col_idx and col_idx['exhibit'] < len(row) else ''
        if not exhibit:
            continue  # skip blank rows
        m = {}
        for key, idx in col_idx.items():
            m[key] = row[idx].strip() if idx < len(row) else ''
        machines.append(m)
    return machines

index = {}   # safe_name → display_name
tabs_done = []

for ws in machine_tabs:
    safe = re.sub(r'[/\\]', '-', ws.title)
    log(f'Reading "{ws.title}"…')
    try:
        machines = _read_machines(ws)
        cache = {'tab': ws.title, 'machines': machines}
        cache_path = os.path.join(out_dir, f'pulseboard-{safe}.json')
        with open(cache_path, 'w', encoding='utf-8') as f:
            json.dump(cache, f, ensure_ascii=False, indent=2)
        index[safe] = ws.title
        tabs_done.append(ws.title)
        log(f'  {len(machines)} machine(s) cached.', 'ok')
    except Exception as e:
        log(f'  Error reading "{ws.title}": {e}', 'error')

# Write index
idx_path = os.path.join(out_dir, 'pulseboard-index.json')
with open(idx_path, 'w', encoding='utf-8') as f:
    json.dump(index, f, ensure_ascii=False)
log('Saved pulseboard-index.json.', 'ok')

log('Done!', 'ok')
print(json.dumps({'ok': True, 'tabs': tabs_done, 'logs': logs}))
