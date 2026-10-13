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
    ws.batch_update([{'range': f'A1:{last_col}1', 'values': [HEADERS]}])
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

def _find_tab(name):
    # Match the tab ignoring case and surrounding spaces: Google treats "TEST"
    # and "Test" as the same name (so we can't create a second one), but
    # wb.worksheet() only finds an exact match.
    want = name.strip().casefold()
    for w in wb.worksheets():
        if w.title.strip().casefold() == want:
            return w
    return None

ws = _find_tab(tab)
if ws is not None and ws.title != tab:
    log(f'Using existing tab "{ws.title}" for "{tab}".', 'info')
if ws is None:
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
    ws.batch_update([{'range': gspread.utils.rowcol_to_a1(1, start_col) + ':' +
                               gspread.utils.rowcol_to_a1(1, start_col + len(missing) - 1),
                      'values': [missing]}])
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
    # Insert a new row directly below the last machine in the Exhibit column.
    # (Not append_row: Google's append guesses where the "table" ends and, when
    # other columns such as Notes have text further down, writes into the wrong
    # columns/rows.) Inserting shifts anything below down, so nothing is overwritten.
    vals = ws.col_values(exhibit_col)
    last = max([i for i, v in enumerate(vals, start=1) if str(v).strip()] or [1])
    row  = last + 1
    new_row = [''] * len(headers_row)
    new_row[exhibit_col - 1] = exhibit
    log(f'Exhibit "{exhibit}" not found — inserting row {row}...', 'info')
    ws.insert_row(new_row, index=row, value_input_option='RAW')
    if _find_row() != row:
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
# The dashboard and the client share page read sheets/{id}/pulseboard-{tab}.json.
# Update this machine's entry there, under the sheet's real tab name. Rows typed
# into the sheet show up after a Fetch or the tab's reload button.

def _merge_this_machine(machines):
    entry = next((m for m in machines if _norm(m.get('exhibit')) == _norm(exhibit)), None)
    if entry is None:
        entry = {'exhibit': exhibit}
        machines.append(entry)
    for key, val in (('host', host), ('ip', ip), ('user', user), ('os', os_info),
                     ('memory', memory), ('disk', disk), ('uptime', uptime),
                     ('last_reboot', last_reboot), ('status', status), ('time', time_str),
                     ('crash_times', crash_times), ('teamviewer_id', teamviewer_id)):
        if val:
            entry[key] = val
    if crashes is not None:
        entry['crashes'] = str(crashes)
    return machines

real_tab = ws.title
try:
    safe       = re.sub(r'[/\\]', '-', real_tab)
    cache_dir  = os.path.join(_here, '..', 'sheets', sheet_id)
    cache_path = os.path.join(cache_dir, f'pulseboard-{safe}.json')
    os.makedirs(cache_dir, exist_ok=True)

    machines = []
    # Start from this tab's cache, or the one an earlier report saved under the sent name
    for p in (cache_path, os.path.join(cache_dir, 'pulseboard-' + re.sub(r'[/\\]', '-', tab) + '.json')):
        if os.path.exists(p):
            try:
                machines = (json.load(open(p, encoding='utf-8')) or {}).get('machines', [])
                break
            except Exception:
                pass
    machines = _merge_this_machine(machines)
    log('JSON cache updated.', 'ok')

    with open(cache_path, 'w', encoding='utf-8') as f:
        json.dump({'tab': real_tab, 'machines': machines}, f, ensure_ascii=False, indent=2)

    # Keep pulseboard-index.json in sync so the dashboard always sees this tab
    idx_path = os.path.join(cache_dir, 'pulseboard-index.json')
    try:
        idx = json.load(open(idx_path, encoding='utf-8')) if os.path.exists(idx_path) else {}
    except Exception:
        idx = {}
    changed = idx.get(safe) != real_tab
    idx[safe] = real_tab

    # If the machine sent a differently written name (e.g. "TEST" for "Test "),
    # drop the duplicate entry an earlier report made under that name.
    sent_safe = re.sub(r'[/\\]', '-', tab)
    if sent_safe != safe and sent_safe in idx:
        del idx[sent_safe]
        try:
            os.remove(os.path.join(cache_dir, f'pulseboard-{sent_safe}.json'))
        except OSError:
            pass
        changed = True
        log(f'Merged duplicate tab entry "{tab}" into "{real_tab}".', 'info')

    if changed:
        with open(idx_path, 'w', encoding='utf-8') as f:
            json.dump(idx, f, ensure_ascii=False, indent=2)
        log('pulseboard-index.json updated.', 'ok')

    # Point any client share link made under the sent name at the real tab
    share_dir = os.path.join(_here, '..', 'shares', 'pulse-group-readonly')
    if real_tab != tab and os.path.isdir(share_dir):
        shares = []
        for fn in os.listdir(share_dir):
            if fn.endswith('.json'):
                try:
                    shares.append((fn, json.load(open(os.path.join(share_dir, fn), encoding='utf-8'))))
                except Exception:
                    pass
        has_real = any(d.get('sheet_id') == sheet_id and d.get('tab') == real_tab for _, d in shares)
        for fn, d in shares:
            if d.get('sheet_id') == sheet_id and d.get('tab') == tab and not has_real:
                d['tab'], d['tab_safe'] = real_tab, safe
                with open(os.path.join(share_dir, fn), 'w', encoding='utf-8') as f:
                    json.dump(d, f, ensure_ascii=False, indent=4)
                log(f'Client link moved to tab "{real_tab}".', 'info')
                has_real = True
except Exception as e:
    log(f'Cache update failed: {e}', 'warn')

log('Done.', 'ok')
print(json.dumps({'ok': True, 'tab': real_tab, 'logs': logs}))
