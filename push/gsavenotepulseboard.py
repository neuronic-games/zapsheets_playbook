#!/usr/bin/env python3
"""
gsavenotepulseboard.py — save a note for one machine in the PulseBoard sheet.

Arg:  "{sheet_id}|{base64(json)}"
JSON: tab, exhibit, notes
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

tab     = data.get('tab',     '').strip()
exhibit = data.get('exhibit', '').strip()
notes   = data.get('notes',   '')          # allow empty string to clear

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

# ── Find the tab and exhibit row ──────────────────────────────────────────────

try:
    ws = wb.worksheet(tab)
except Exception as e:
    fail(f'Tab "{tab}" not found: {e}')

cell = ws.find(exhibit)
if cell is None:
    fail(f'Exhibit "{exhibit}" not found in tab "{tab}"')

# Find Notes column from header row
headers = ws.row_values(1)
try:
    notes_col = next(i + 1 for i, h in enumerate(headers) if h.strip().lower() == 'notes')
except StopIteration:
    fail('Notes column not found in sheet header')

row = cell.row
col_letter = chr(ord('A') + notes_col - 1)
ws.update([[notes]], f'{col_letter}{row}')
log(f'Notes updated for "{exhibit}" in {col_letter}{row}.', 'ok')

# ── Update JSON cache ─────────────────────────────────────────────────────────

try:
    safe       = re.sub(r'[/\\]', '-', tab)
    cache_path = os.path.join(_here, '..', 'sheets', sheet_id, f'pulseboard-{safe}.json')

    if os.path.exists(cache_path):
        with open(cache_path, encoding='utf-8') as f:
            cache = json.load(f)
        machines = cache.get('machines', [])
        entry = next((m for m in machines if m.get('exhibit') == exhibit), None)
        if entry is not None:
            entry['notes'] = notes
            with open(cache_path, 'w', encoding='utf-8') as f:
                json.dump(cache, f, ensure_ascii=False, indent=2)
            log('JSON cache updated.', 'ok')
except Exception as e:
    log(f'Cache update failed: {e}', 'warn')

log('Done.', 'ok')
print(json.dumps({'ok': True, 'logs': logs}))
