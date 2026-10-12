#!/usr/bin/env python3
"""
gpulsepulseboard.py — receive a machine heartbeat and write it to the PulseBoard sheet.

Arg:  "{sheet_id}|{base64(json)}"
JSON: tab, exhibit, host, ip, status, time, crashes, crash_times

Returns: {"ok": true, "logs": [...]} or {"ok": false, "error": "..."}
"""

import sys, os, json, base64, re

_here     = os.path.dirname(os.path.abspath(__file__))
cred_path = os.path.join(_here, '..', 'credentials.json')

logs = []
def log(msg, t='info'): logs.append({'msg': msg, 'type': t})
def fail(msg):
    print(json.dumps({'ok': False, 'error': msg, 'logs': logs}))
    sys.exit(1)

# ── Parse args ────────────────────────────────────────────────────────────────

arg = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
if '|' not in arg:
    fail('Invalid argument — expected sheet_id|base64(json)')

sheet_id, b64 = arg.split('|', 1)
try:
    data = json.loads(base64.b64decode(b64).decode('utf-8'))
except Exception as e:
    fail(f'Invalid payload: {e}')

tab         = data.get('tab',         '').strip()
exhibit     = data.get('exhibit',     '').strip()
host        = data.get('host',        '').strip()
ip          = data.get('ip',          '').strip()
user        = data.get('user',        '').strip()
os_info     = data.get('os',          '').strip()
memory      = data.get('memory',      '').strip()
disk        = data.get('disk',        '').strip()
uptime      = data.get('uptime',      '').strip()
last_reboot    = data.get('last_reboot',    '').strip()
teamviewer_id  = data.get('teamviewer_id', '').strip()
status         = data.get('status',        '').strip()
time_str    = data.get('time',        '').strip()
crashes     = data.get('crashes',     None)
crash_times = data.get('crash_times', '').strip()

if not sheet_id: fail('sheet_id required')
if not exhibit:  fail('exhibit required')
if not tab:      fail('tab required')

# ── Connect ───────────────────────────────────────────────────────────────────

if not os.path.exists(cred_path):
    fail('credentials.json not found on server')

try:
    import gspread
    sa = gspread.service_account(filename=cred_path)
except ImportError:
    fail('gspread not installed on server')
except Exception as e:
    fail(f'Auth failed: {e}')

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    fail(f'Could not open spreadsheet: {e}')

# ── Get or create the tab ─────────────────────────────────────────────────────

HEADERS    = ['Exhibit', 'Host', 'IP', 'User', 'OS', 'Memory', 'Disk', 'Uptime', 'Last Reboot', 'Status', 'Time', 'Crashes', 'Crash Times', 'TeamViewer ID', 'Notes']
COL_WIDTHS = [160, 160, 130, 120, 150, 100, 100, 90, 110, 90, 170, 80, 300, 120, 250]

def setup_worksheet(ws):
    last_col = chr(ord('A') + len(HEADERS) - 1)
    ws.update([HEADERS], f'A1:{last_col}1')
    ws.format(f'A1:{last_col}1', {
        'backgroundColor': {'red': 0.15, 'green': 0.15, 'blue': 0.15},
        'textFormat': {'bold': True, 'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0}},
        'horizontalAlignment': 'CENTER',
    })
    wb.batch_update({'requests': [
        {
            'updateDimensionProperties': {
                'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': i, 'endIndex': i + 1},
                'properties': {'pixelSize': width},
                'fields': 'pixelSize',
            }
        }
        for i, width in enumerate(COL_WIDTHS)
    ] + [
        {
            'updateSheetProperties': {
                'properties': {
                    'sheetId': ws.id,
                    'gridProperties': {'frozenRowCount': 1},
                },
                'fields': 'gridProperties.frozenRowCount',
            }
        }
    ]})

try:
    ws = wb.worksheet(tab)
except gspread.exceptions.WorksheetNotFound:
    log(f'Tab "{tab}" not found — creating...', 'info')
    ws = wb.add_worksheet(title=tab, rows=100, cols=len(HEADERS))
    setup_worksheet(ws)
    log(f'Tab "{tab}" created.', 'ok')

# ── Map header names → column numbers (columns may be in any order) ──────────

def _norm(v):
    return str(v or '').strip().lstrip("'").strip().casefold()

headers_row = ws.row_values(1)
if not any(h.strip() for h in headers_row):
    log(f'Tab "{tab}" has no header row — adding headers...', 'info')
    if ws.col_count < len(HEADERS):
        wb.batch_update({'requests': [{'appendDimension': {
            'sheetId': ws.id, 'dimension': 'COLUMNS', 'length': len(HEADERS) - ws.col_count}}]})
    setup_worksheet(ws)
    headers_row = list(HEADERS)

col = {}
for i, h in enumerate(headers_row):
    k = _norm(h)
    if k and k not in col:
        col[k] = i + 1

# Add any standard columns the tab is missing, at the end (never reorder existing ones)
missing = [h for h in HEADERS if _norm(h) not in col]
if missing:
    need = len(headers_row) + len(missing)
    if ws.col_count < need:
        wb.batch_update({'requests': [{'appendDimension': {
            'sheetId': ws.id, 'dimension': 'COLUMNS', 'length': need - ws.col_count}}]})
    start_col = len(headers_row) + 1
    ws.update([missing], gspread.utils.rowcol_to_a1(1, start_col) + ':' +
              gspread.utils.rowcol_to_a1(1, start_col + len(missing) - 1))
    for j, h in enumerate(missing):
        col[_norm(h)] = start_col + j
    headers_row = headers_row + missing
    log(f'Added missing columns: {", ".join(missing)}', 'info')

exhibit_col = col[_norm('Exhibit')]

# ── Find or add the exhibit row ───────────────────────────────────────────────

def _find_row():
    vals = ws.col_values(exhibit_col)
    want = _norm(exhibit)
    for i, v in enumerate(vals[1:], start=2):   # skip header
        if _norm(v) == want:
            return i
    return None

row = _find_row()
if row is None:
    log(f'Exhibit "{exhibit}" not found — adding row...', 'info')
    new_row = [''] * len(headers_row)
    new_row[exhibit_col - 1] = exhibit
    res = ws.append_row(new_row, value_input_option='RAW', table_range='A1')
    # Google tells us where it landed, e.g. "'CMH'!A14:O14"
    m = re.search(r'![A-Z]+(\d+)', ((res or {}).get('updates') or {}).get('updatedRange', ''))
    row = int(m.group(1)) if m else _find_row()
    if row is None:
        fail(f'Could not add a row for exhibit "{exhibit}" on tab "{tab}"')
    log(f'Added "{exhibit}" at row {row}.', 'ok')

# ── Write the data (by header name) ───────────────────────────────────────────

FIELDS = [
    ('Host', host), ('IP', ip), ('User', user), ('OS', os_info), ('Memory', memory),
    ('Disk', disk), ('Uptime', uptime), ('Last Reboot', last_reboot), ('Status', status),
    ('Time', time_str), ('Crashes', crashes), ('Crash Times', crash_times),
    ('TeamViewer ID', teamviewer_id),
]
updates = []
for header, value in FIELDS:
    if value is None or value == '':
        continue
    c = col.get(_norm(header))
    if c:
        updates.append({'range': gspread.utils.rowcol_to_a1(row, c), 'values': [[value]]})

if updates:
    ws.batch_update(updates)
    log(f'Updated row {row} for "{exhibit}".', 'ok')

# ── Update JSON cache ─────────────────────────────────────────────────────────

try:
    safe       = re.sub(r'[/\\]', '-', tab)
    cache_dir  = os.path.join(_here, '..', 'sheets', sheet_id)
    cache_path = os.path.join(cache_dir, f'pulseboard-{safe}.json')
    os.makedirs(cache_dir, exist_ok=True)

    cache = {'tab': tab, 'machines': []}
    if os.path.exists(cache_path):
        try:
            cache = json.load(open(cache_path, encoding='utf-8')) or cache
        except Exception:
            pass

    # Update or insert the machine entry
    machines = cache.get('machines', [])
    entry = next((m for m in machines if m.get('exhibit') == exhibit), None)
    if entry is None:
        entry = {'exhibit': exhibit}
        machines.append(entry)

    if host:                entry['host']        = host
    if ip:                  entry['ip']          = ip
    if user:                entry['user']        = user
    if os_info:             entry['os']          = os_info
    if memory:              entry['memory']      = memory
    if disk:                entry['disk']        = disk
    if uptime:              entry['uptime']      = uptime
    if last_reboot:         entry['last_reboot'] = last_reboot
    if status:              entry['status']      = status
    if time_str:            entry['time']        = time_str
    if crashes is not None: entry['crashes']      = str(crashes)
    if crash_times:         entry['crash_times']  = crash_times
    if teamviewer_id:       entry['teamviewer_id'] = teamviewer_id

    cache['machines'] = machines
    with open(cache_path, 'w', encoding='utf-8') as f:
        json.dump(cache, f, ensure_ascii=False, indent=2)
    log('JSON cache updated.', 'ok')

    # Keep pulseboard-index.json in sync so the dashboard always sees this tab
    idx_path = os.path.join(cache_dir, 'pulseboard-index.json')
    try:
        idx = json.load(open(idx_path, encoding='utf-8')) if os.path.exists(idx_path) else {}
    except Exception:
        idx = {}
    if idx.get(safe) != tab:
        idx[safe] = tab
        with open(idx_path, 'w', encoding='utf-8') as f:
            json.dump(idx, f, ensure_ascii=False, indent=2)
        log('pulseboard-index.json updated.', 'ok')
except Exception as e:
    log(f'Cache update failed: {e}', 'warn')

log('Done.', 'ok')
print(json.dumps({'ok': True, 'logs': logs}))
