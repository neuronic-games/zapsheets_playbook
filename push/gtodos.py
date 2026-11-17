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

    def write(ws, rng, values):
        """Write values at an A1 range. Uses the plain Sheets API call, which every gspread
        version has (ws.update's argument order differs between versions)."""
        title = "'" + ws.title.replace("'", "''") + "'"
        wb.values_update(title + '!' + rng, params={'valueInputOption': 'RAW'}, body={'values': values})

    def write_many(ws, updates):
        """[{'range': 'B3', 'values': [[...]]}, ...] in one call."""
        title = "'" + ws.title.replace("'", "''") + "'"
        wb.values_batch_update({'valueInputOption': 'RAW',
                                'data': [{'range': title + '!' + u['range'], 'values': u['values']} for u in updates]})

    def append(ws, rows_):
        title = "'" + ws.title.replace("'", "''") + "'"
        wb.values_append(title + '!A1', params={'valueInputOption': 'RAW', 'insertDataOption': 'INSERT_ROWS'},
                         body={'values': rows_})

    def clear(ws, rng):
        wb.values_clear("'" + ws.title.replace("'", "''") + "'!" + rng)


    # Column widths (px), same look as the [Game] dev tabs
    WIDTHS = {'ID': 95, 'Game': 150, 'Session': 180, 'Observation': 260, 'Thoughts': 380,
              'Priority': 85, 'Due': 100, 'Assigned': 150, 'Status': 80, 'Created': 100}

    def format_sheet(ws, headers):
        """Heading row, widths, wrapping and highlight rules like gcreatedevtab.py."""
        n = len(headers)
        last = rowcol_to_a1(1, n)
        reqs = [
            {'updateSheetProperties': {'properties': {'sheetId': ws.id, 'gridProperties': {'frozenRowCount': 1}},
                                       'fields': 'gridProperties.frozenRowCount'}},
            {'repeatCell': {'range': {'sheetId': ws.id, 'startRowIndex': 0, 'endRowIndex': 1, 'startColumnIndex': 0, 'endColumnIndex': n},
                            'cell': {'userEnteredFormat': {
                                'backgroundColor': {'red': 0.102, 'green': 0.373, 'blue': 0.478},
                                'textFormat': {'bold': True, 'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0}}}},
                            'fields': 'userEnteredFormat(backgroundColor,textFormat)'}},
        ]
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
        wb.batch_update({'requests': reqs})

    created = False
    ws = next((w for w in wb.worksheets() if w.title.lower() == TAB), None)
    if ws is None:
        created = True
        ws = wb.add_worksheet(title=TAB, rows=200, cols=len(HEADERS))
        write(ws, 'A1', [HEADERS])
        try:
            format_sheet(ws, HEADERS)
        except Exception:
            pass   # formatting is cosmetic

    values  = ws.get_all_values() or [HEADERS]
    # Repair: if the heading row is missing (e.g. overwritten by data), put it back on top
    if 'ID' not in [h.strip() for h in values[0]]:
        wb.batch_update({'requests': [{'insertDimension': {'range': {'sheetId': ws.id, 'dimension': 'ROWS', 'startIndex': 0, 'endIndex': 1}, 'inheritFromBefore': False}}]})
        write(ws, 'A1', [HEADERS])
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
        write(ws, rowcol_to_a1(1, len(headers) + 1), [missing])
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

    def scan_notes():
        """Every observation row with a "TODO" note in the [Game] dev tabs."""
        dev = [w.title for w in wb.worksheets() if w.title.lower().endswith('] dev')]
        if not dev:
            return []
        meta = wb.fetch_sheet_metadata(params={
            'includeGridData': 'true',
            'ranges': ["'" + t.replace("'", "''") + "'" for t in dev],
            'fields': 'sheets(properties.title,data.rowData.values(formattedValue,note,userEnteredValue))',
        })
        def text(c):
            v = c.get('formattedValue', '')
            if not v:
                fv = (c.get('userEnteredValue') or {}).get('formulaValue', '')
                if fv.strip().upper().startswith('=IMAGE('): v = fv.strip()
            return clean(v)
        found = []
        for sh in meta.get('sheets', []):
            title = sh['properties']['title']
            game  = title[1:title.rfind(']')] if title.startswith('[') else title
            rd    = (sh.get('data') or [{}])[0].get('rowData', [])
            if not rd: continue
            hdr = [text(c) for c in rd[0].get('values', [])]
            def ix(*names): return next((i for i, h in enumerate(hdr) if h in names), -1)
            iD, iE, iP = ix('Date'), ix('Event'), ix('People')
            iO, iS = ix('Observations', 'Observation'), ix('Solutions', 'Thoughts', 'Solution')
            session = ''
            for row in rd[1:]:
                cells = row.get('values', [])
                val = lambda i: text(cells[i]) if 0 <= i < len(cells) else ''
                if val(iD) or val(iE):
                    d = val(iD); parts = d.split('-')
                    if len(parts) == 3 and len(parts[0]) == 4: d = parts[1] + '/' + parts[2] + '/' + parts[0]
                    session = ' '.join(x for x in (val(iE), val(iP)) if x) + (' · ' + d if d else '')
                    continue
                if any(str(c.get('note', '')).strip().upper() == 'TODO' for c in cells):
                    if val(iO) or val(iS):
                        found.append({'game': game, 'session': session, 'obs': val(iO), 'sol': val(iS)})
        return found

    def add_items(items, reopen):
        """Append TODOs that aren't in the sheet yet; returns (added, reopened)."""
        new_rows, re_ups = [], []
        for it in items:
            game, obs, sol = clean(it.get('game')), clean(it.get('obs')), clean(it.get('sol'))
            if not game or not (obs or sol):
                continue
            i = find_text(game, obs, sol)
            if i >= 0:
                if reopen and cell(rows[i], 'Status').lower() == 'done':
                    re_ups.append({'range': a1(i, 'Status'), 'values': [['Open']]})
                continue
            r = [''] * len(headers)
            r[C['ID']]          = ''.join(random.choices(string.ascii_uppercase + string.digits, k=8))
            r[C['Game']]        = game
            r[C['Session']]     = clean(it.get('session'))
            r[C['Observation']] = obs
            r[C['Thoughts']]    = sol
            r[C['Status']]      = 'Open'
            r[C['Created']]     = datetime.date.today().isoformat()
            new_rows.append(r); rows.append(r)
        if new_rows:
            append(ws, new_rows)
        if re_ups:
            write_many(ws, re_ups)
        return len(new_rows), len(re_ups)

    # A new (or re-created) todos tab is first rebuilt from the TODO notes in the session tabs
    if created or action == 'sync':
        add_items(scan_notes(), False)

    # Find a TODO by ID, or — if the page's ID is from before the tab was re-created — by game + text
    def find_ref():
        i = find_id(clean(data.get('id'))) if data.get('id') else -1
        if i < 0 and (data.get('obs') or data.get('sol')):
            i = find_text(clean(data.get('game')), clean(data.get('obs')), clean(data.get('sol')))
        return i

    if action in ('add', 'sync'):
        added, reopened = add_items(data.get('items', []), action == 'add')
        out({'ok': True, 'added': added, 'reopened': reopened, 'created': created})


    if action == 'update':
        i = find_ref()
        if i < 0: out({'ok': True, 'found': False, 'created': created})   # unknown TODO — the refreshed list is returned
        for h in ('Priority', 'Due', 'Assigned'):
            if h in (data.get('fields') or {}):
                updates.append({'range': a1(i, h), 'values': [[clean(data['fields'][h])]]})
        if updates: write_many(ws, updates)
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
            write(ws, 'A2:' + rowcol_to_a1(len(ordered) + 1, width), ordered)
        if len(ordered) < len(rows):   # duplicates removed: clear the leftover rows at the bottom
            clear(ws, 'A' + str(len(ordered) + 2) + ':' + rowcol_to_a1(len(rows) + 1, len(headers)))
        out({'ok': True})

    if action == 'status':
        status = 'Done' if clean(data.get('status')).lower() == 'done' else 'Open'
        i = find_ref()
        if i < 0: out({'ok': True, 'found': False})   # not in the todos sheet (e.g. flagged by hand) — nothing to do
        write_many(ws, [{'range': a1(i, 'Status'), 'values': [[status]]}])
        out({'ok': True, 'found': True})

    out({'error': 'Unknown action'})


try:
    main()
except SystemExit:
    raise
except Exception as e:
    print(json.dumps({'error': f'{type(e).__name__}: {e}'}))
