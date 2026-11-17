# gtodos.py — the DevBoard "todos" sheet: one row per TODO (observation flagged with the light bulb).
#
# Arg: {sheet_id}|{base64_json}
# JSON: { "action": "add" | "update" | "order" | "status", ... }
#   add    — items: [{game, session, obs, sol}]   adds new TODOs (re-opens ones already there)
#   update — id, fields: {Priority, Due, Assigned}
#   order  — ids: [id, ...]                       moves the rows so the sheet is in the Board's order
#   status — status: "Done" | "Open", and id  or  game + obs + sol
# Prints {"ok": true, ...} or {"error": "..."}.

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, base64, socket, random, string, datetime
from gspread.utils import rowcol_to_a1

socket.setdefaulttimeout(30)
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
TAB     = 'todos'
HEADERS = ['ID', 'Game', 'Session', 'Observation', 'Thoughts', 'Priority', 'Due', 'Assigned', 'Status', 'Created']
# The list order IS the row order in the sheet (no order column).

def out(d):
    print(json.dumps(d)); sys.exit(0)

def clean(v):
    return str(v if v is not None else '').strip().lstrip("'").strip()

try:
    sheet_id, enc = sys.argv[1].split('|', 1)
    data = json.loads(base64.b64decode(enc).decode('utf-8'))
except Exception as e:
    out({'error': f'Bad argument: {e}'})

try:
    wb = gspread.service_account(filename=credFileName).open_by_key(sheet_id)
except Exception as e:
    out({'error': f'Could not open spreadsheet: {e}'})

ws = next((w for w in wb.worksheets() if w.title.lower() == TAB), None)
if ws is None:
    if data.get('action') != 'add':
        out({'error': 'There is no todos sheet yet'})
    ws = wb.add_worksheet(title=TAB, rows=200, cols=len(HEADERS))
    ws.update(range_name='A1', values=[HEADERS], value_input_option='RAW')
    try:
        ws.freeze(rows=1)
        ws.format('A1:J1', {'textFormat': {'bold': True}})
    except Exception:
        pass

values  = ws.get_all_values() or [HEADERS]
# Repair: if the heading row is missing (e.g. overwritten by data), put it back on top
if 'ID' not in [h.strip() for h in values[0]]:
    ws.insert_row(HEADERS, 1, value_input_option='RAW')
    values = [HEADERS] + values
headers = [h.strip() for h in values[0]]
# Add any missing columns at the end
missing = [h for h in HEADERS if h not in headers]
if missing:
    if ws.col_count < len(headers) + len(missing):
        ws.add_cols(len(headers) + len(missing) - ws.col_count)
    ws.update(range_name=rowcol_to_a1(1, len(headers) + 1), values=[missing], value_input_option='RAW')
    headers += missing
C = {h: headers.index(h) for h in HEADERS}
rows = values[1:]

def cell(r, h):
    return clean(r[C[h]]) if C[h] < len(r) else ''

def find_id(tid):
    return next((i for i, r in enumerate(rows) if cell(r, 'ID') == tid), -1)

def find_text(game, obs, sol):
    return next((i for i, r in enumerate(rows)
                 if cell(r, 'Game').lower() == game.lower() and cell(r, 'Observation') == obs and cell(r, 'Thoughts') == sol), -1)

def a1(i, h):   # i = index into rows
    return rowcol_to_a1(i + 2, C[h] + 1)

action = data.get('action')
updates = []

if action == 'add':
    new_rows, added = [], 0
    for it in data.get('items', []):
        game, obs, sol = clean(it.get('game')), clean(it.get('obs')), clean(it.get('sol'))
        if not game or not (obs or sol):
            continue
        i = find_text(game, obs, sol)
        if i >= 0:
            if cell(rows[i], 'Status').lower() == 'done':
                updates.append({'range': a1(i, 'Status'), 'values': [['Open']]})
            continue
        r = [''] * len(headers)
        r[C['ID']]          = ''.join(random.choices(string.ascii_uppercase + string.digits, k=8))
        r[C['Game']]        = game
        r[C['Session']]     = clean(it.get('session'))
        r[C['Observation']] = obs
        r[C['Thoughts']]    = sol
        r[C['Status']]      = 'Open'
        r[C['Created']]     = datetime.date.today().isoformat()
        new_rows.append(r); rows.append(r); added += 1
    if new_rows:
        ws.append_rows(new_rows, value_input_option='RAW', insert_data_option='INSERT_ROWS', table_range='A1')
    if updates:
        ws.batch_update(updates, value_input_option='RAW')
    out({'ok': True, 'added': added, 'reopened': len(updates)})

if action == 'update':
    i = find_id(clean(data.get('id')))
    if i < 0: out({'error': 'TODO not found — reload and try again'})
    for h in ('Priority', 'Due', 'Assigned'):
        if h in (data.get('fields') or {}):
            updates.append({'range': a1(i, h), 'values': [[clean(data['fields'][h])]]})
    if updates: ws.batch_update(updates, value_input_option='RAW')
    out({'ok': True})

if action == 'order':
    # Rewrite the data rows in the new order (rows not in the list keep their place at the end)
    by_id, rest = {}, []
    for r in rows:
        tid = cell(r, 'ID')
        if not tid: rest.append(r)
        elif tid not in by_id: by_id[tid] = r     # duplicate IDs are dropped
    ordered = [by_id.pop(clean(t)) for t in data.get('ids', []) if clean(t) in by_id]
    ordered += [r for r in rows if cell(r, 'ID') in by_id] + rest
    if ordered:
        width = len(headers)
        ordered = [(list(r) + [''] * width)[:width] for r in ordered]
        # Explicit keywords: older gspread versions take (range, values) in the other order
        ws.update(range_name='A2:' + rowcol_to_a1(len(ordered) + 1, width), values=ordered, value_input_option='RAW')
    if len(ordered) < len(rows):   # duplicates removed: clear the leftover rows at the bottom
        ws.batch_clear(['A' + str(len(ordered) + 2) + ':' + rowcol_to_a1(len(rows) + 1, len(headers))])
    out({'ok': True})

if action == 'status':
    status = 'Done' if clean(data.get('status')).lower() == 'done' else 'Open'
    i = find_id(clean(data.get('id'))) if data.get('id') else find_text(clean(data.get('game')), clean(data.get('obs')), clean(data.get('sol')))
    if i < 0: out({'ok': True, 'found': False})   # not in the todos sheet (e.g. flagged by hand) — nothing to do
    ws.batch_update([{'range': a1(i, 'Status'), 'values': [[status]]}], value_input_option='RAW')
    out({'ok': True, 'found': True})

out({'error': 'Unknown action'})
