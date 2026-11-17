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

import warnings
warnings.filterwarnings('ignore')   # never let a library warning spoil the JSON answer

def main():
    try:
        sheet_id, enc = sys.argv[1].split('|', 1)
        data = json.loads(base64.b64decode(enc).decode('utf-8'))
    except Exception as e:
        out({'error': f'Bad argument: {e}'})

    try:
        wb = gspread.service_account(filename=credFileName).open_by_key(sheet_id)
    except Exception as e:
        out({'error': f'Could not open spreadsheet: {e}'})

    # Column widths (px), same look as the [Game] dev tabs
    WIDTHS = {'ID': 95, 'Game': 150, 'Session': 180, 'Observation': 260, 'Thoughts': 380,
              'Priority': 85, 'Due': 100, 'Assigned': 150, 'Status': 80, 'Created': 100}

    def format_sheet(ws, headers):
        """Heading row, widths, wrapping and highlight rules like gcreatedevtab.py."""
        n = len(headers)
        last = rowcol_to_a1(1, n)
        ws.freeze(rows=1)
        ws.format('A1:' + last, {
            'backgroundColor': {'red': 0.102, 'green': 0.373, 'blue': 0.478},
            'textFormat': {'bold': True, 'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0}},
        })
        reqs = []
        for i, h in enumerate(headers):
            if h in WIDTHS:
                reqs.append({'updateDimensionProperties': {
                    'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': i, 'endIndex': i + 1},
                    'properties': {'pixelSize': WIDTHS[h]}, 'fields': 'pixelSize'}})
        rows_range = {'sheetId': ws.id, 'startRowIndex': 1, 'endRowIndex': 1000, 'startColumnIndex': 0, 'endColumnIndex': n}
        # Wrap the text columns; everything aligned to the top so long notes read well
        reqs.append({'repeatCell': {'range': rows_range,
                     'cell': {'userEnteredFormat': {'verticalAlignment': 'TOP'}}, 'fields': 'userEnteredFormat.verticalAlignment'}})
        for h in ('Observation', 'Thoughts'):
            if h in headers:
                i = headers.index(h)
                reqs.append({'repeatCell': {'range': dict(rows_range, startColumnIndex=i, endColumnIndex=i + 1),
                             'cell': {'userEnteredFormat': {'wrapStrategy': 'WRAP'}}, 'fields': 'userEnteredFormat.wrapStrategy'}})
        def col_letter(h):
            return rowcol_to_a1(1, headers.index(h) + 1).rstrip('1')
        rules = []
        if 'Status' in headers:   # Done → gray, struck through
            rules.append((rows_range, '=$' + col_letter('Status') + '2="Done"',
                          {'textFormat': {'strikethrough': True, 'foregroundColor': {'red': .6, 'green': .6, 'blue': .6}}}))
        if 'Priority' in headers:
            i = headers.index('Priority'); pr = dict(rows_range, startColumnIndex=i, endColumnIndex=i + 1)
            L = col_letter('Priority')
            rules += [(pr, '=$' + L + '2="High"',   {'backgroundColor': {'red': .99, 'green': .91, 'blue': .91}, 'textFormat': {'foregroundColor': {'red': .71, 'green': .14, 'blue': .09}}}),
                      (pr, '=$' + L + '2="Medium"', {'backgroundColor': {'red': 1.0, 'green': .96, 'blue': .84}, 'textFormat': {'foregroundColor': {'red': .64, 'green': .37, 'blue': .07}}}),
                      (pr, '=$' + L + '2="Low"',    {'backgroundColor': {'red': .91, 'green': .96, 'blue': .93}, 'textFormat': {'foregroundColor': {'red': .18, 'green': .49, 'blue': .20}}})]
        for k, (rng, formula, fmt) in enumerate(rules):
            reqs.append({'addConditionalFormatRule': {'index': k, 'rule': {'ranges': [rng], 'booleanRule': {
                'condition': {'type': 'CUSTOM_FORMULA', 'values': [{'userEnteredValue': formula}]}, 'format': fmt}}}})
        ws.spreadsheet.batch_update({'requests': reqs})

    ws = next((w for w in wb.worksheets() if w.title.lower() == TAB), None)
    if ws is None:
        if data.get('action') != 'add':
            out({'error': 'There is no todos sheet yet'})
        ws = wb.add_worksheet(title=TAB, rows=200, cols=len(HEADERS))
        ws.update(range_name='A1', values=[HEADERS], value_input_option='RAW')
        try:
            format_sheet(ws, HEADERS)
        except Exception:
            pass   # formatting is cosmetic

    values  = ws.get_all_values() or [HEADERS]
    # Repair: if the heading row is missing (e.g. overwritten by data), put it back on top
    if 'ID' not in [h.strip() for h in values[0]]:
        ws.insert_row(HEADERS, 1, value_input_option='RAW')
        values = [HEADERS] + values
        try:
            format_sheet(ws, HEADERS)
        except Exception:
            pass
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


try:
    main()
except SystemExit:
    raise
except Exception as e:
    print(json.dumps({'error': f'{type(e).__name__}: {e}'}))
