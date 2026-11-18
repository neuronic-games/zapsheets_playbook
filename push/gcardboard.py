# gcardboard.py — CardBoard: list a spreadsheet's tabs, and load / save card layouts.
#
# Arg: {sheet_id}|{base64_json}
# JSON: { "action": "tabs" | "layouts" | "save", ... }
#   tabs    — every tab except the "cardboard" tab: [{title, rows, cols}]
#   layouts — {sheet title: layout} from the "cardboard" tab
#   save    — sheet: tab title, layout: {...}  → one row per sheet in the "cardboard" tab
#   addfield — sheet: tab title, name: new column name → added after the last column heading
#   grid    — sheet → {header, rows: [{row, values}]} as typed in the sheet (formulas, not results)
#   setcell — sheet, row, field, value, old → writes one cell (as if typed); refuses if the cell
#             no longer holds "old" (someone changed it in the meantime)
#   addrow  — sheet, values: {field: value} → appended after the last row
# Writes use the plain Sheets API calls, which work the same with gspread 5 and 6.

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, base64, socket, datetime, warnings
warnings.filterwarnings('ignore')
socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
TAB     = 'cardboard'
HEADERS = ['Sheet', 'Layout', 'Updated']

def out(d):
    print(json.dumps(d, ensure_ascii=False)); sys.exit(0)

def q(title):
    return "'" + title.replace("'", "''") + "'"

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

    action = data.get('action')
    sheets = wb.worksheets()

    if action == 'tabs':
        out({'ok': True, 'title': wb.title,
             'tabs': [{'title': w.title, 'rows': w.row_count, 'cols': w.col_count}
                      for w in sheets if w.title.lower() != TAB]})

    ws = next((w for w in sheets if w.title.lower() == TAB), None)

    if action == 'layouts':
        if ws is None:
            out({'ok': True, 'layouts': {}})
        vals = wb.values_get(q(ws.title)).get('values', [])
        layouts = {}
        if vals:
            hdr = [h.strip() for h in vals[0]]
            iS = hdr.index('Sheet') if 'Sheet' in hdr else 0
            iL = hdr.index('Layout') if 'Layout' in hdr else 1
            for r in vals[1:]:
                name = r[iS].strip() if iS < len(r) else ''
                raw  = r[iL] if iL < len(r) else ''
                if not name or not raw: continue
                try: layouts[name] = json.loads(raw)
                except Exception: pass
        out({'ok': True, 'layouts': layouts})

    if action == 'save':
        name   = str(data.get('sheet', '')).strip()
        layout = data.get('layout')
        if not name or not isinstance(layout, dict):
            out({'error': 'Missing sheet or layout'})
        raw = json.dumps(layout, ensure_ascii=False, separators=(',', ':'))
        if len(raw) > 49000:
            out({'error': 'This layout is too big to store in one cell — remove some elements'})
        if ws is None:
            ws = wb.add_worksheet(title=TAB, rows=50, cols=len(HEADERS))
            wb.values_update(q(TAB) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [HEADERS]})
            try:
                wb.batch_update({'requests': [
                    {'updateSheetProperties': {'properties': {'sheetId': ws.id, 'gridProperties': {'frozenRowCount': 1}},
                                               'fields': 'gridProperties.frozenRowCount'}},
                    {'repeatCell': {'range': {'sheetId': ws.id, 'startRowIndex': 0, 'endRowIndex': 1, 'startColumnIndex': 0, 'endColumnIndex': 3},
                                    'cell': {'userEnteredFormat': {'backgroundColor': {'red': .23, 'green': .2, 'blue': .45},
                                             'textFormat': {'bold': True, 'foregroundColor': {'red': 1, 'green': 1, 'blue': 1}}}},
                                    'fields': 'userEnteredFormat(backgroundColor,textFormat)'}},
                    {'updateDimensionProperties': {'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': 0, 'endIndex': 1},
                                                   'properties': {'pixelSize': 180}, 'fields': 'pixelSize'}},
                    {'updateDimensionProperties': {'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': 1, 'endIndex': 2},
                                                   'properties': {'pixelSize': 420}, 'fields': 'pixelSize'}},
                ]})
            except Exception:
                pass
            vals = [HEADERS]
        else:
            vals = wb.values_get(q(ws.title)).get('values', []) or [HEADERS]
        row = next((i + 1 for i, r in enumerate(vals) if i > 0 and r and r[0].strip() == name), 0)
        stamp = datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M')
        if row:
            wb.values_update(q(ws.title) + '!A' + str(row) + ':C' + str(row), params={'valueInputOption': 'RAW'},
                             body={'values': [[name, raw, stamp]]})
        else:
            wb.values_append(q(ws.title) + '!A1', params={'valueInputOption': 'RAW', 'insertDataOption': 'OVERWRITE'},
                             body={'values': [[name, raw, stamp]]})
        out({'ok': True, 'saved': name, 'updated': stamp})

    if action == 'addfield':
        name  = str(data.get('sheet', '')).strip()
        field = str(data.get('name', '')).strip()
        tws = next((w for w in sheets if w.title == name), None)
        if tws is None or name.lower() == TAB:
            out({'error': 'That sheet was not found'})
        if not field:
            out({'error': 'Give the field a name'})
        hdr = (wb.values_get(q(tws.title) + '!1:1').get('values') or [[]])[0]
        if any(str(h).strip().lower() == field.lower() for h in hdr):
            out({'error': f'There is already a column called "{field}"'})
        # first column after the last heading
        col = len(hdr)
        while col > 0 and not str(hdr[col - 1]).strip():
            col -= 1
        if col + 1 > tws.col_count:
            tws.add_cols(col + 1 - tws.col_count)
        a1 = gspread.utils.rowcol_to_a1(1, col + 1)
        wb.values_update(q(tws.title) + '!' + a1, params={'valueInputOption': 'RAW'}, body={'values': [[field]]})
        out({'ok': True, 'field': field, 'column': a1.rstrip('0123456789')})

    if action in ('grid', 'setcell', 'addrow'):
        name = str(data.get('sheet', '')).strip()
        tws = next((w for w in sheets if w.title == name), None)
        if tws is None or name.lower() == TAB:
            out({'error': 'That sheet was not found'})

        def read(rng, formulas=True):
            return wb.values_get(q(tws.title) + '!' + rng, params={
                'valueRenderOption': 'FORMULA' if formulas else 'FORMATTED_VALUE',
                'dateTimeRenderOption': 'FORMATTED_STRING'}).get('values', [])

        def cellstr(v):
            if isinstance(v, float) and v.is_integer(): return str(int(v))
            return '' if v is None else str(v)

        hdr = [cellstr(h).strip() for h in ((read('1:1', False) or [[]])[0])]

        if action == 'grid':
            vals = read('A2:' + gspread.utils.rowcol_to_a1(tws.row_count, max(1, len(hdr))).rstrip('0123456789') + str(tws.row_count))
            rows = []
            for i, r in enumerate(vals):
                r = [cellstr(v) for v in r]
                if any(x.strip() for x in r):
                    rows.append({'row': i + 2, 'values': r + [''] * (len(hdr) - len(r))})
            out({'ok': True, 'header': hdr, 'rows': rows})

        if action == 'setcell':
            field = str(data.get('field', ''))
            try: row = int(data.get('row', 0))
            except Exception: row = 0
            if row < 2 or field not in hdr:
                out({'error': 'That cell was not found — reload the data'})
            a1 = gspread.utils.rowcol_to_a1(row, hdr.index(field) + 1)
            cur = read(a1)
            cur = cellstr(cur[0][0]) if cur and cur[0] else ''
            if 'old' in data and cur != str(data.get('old') or ''):
                out({'error': 'conflict', 'current': cur})
            value = str(data.get('value', ''))
            wb.values_update(q(tws.title) + '!' + a1, params={'valueInputOption': 'USER_ENTERED'}, body={'values': [[value]]})
            now = read(a1)
            out({'ok': True, 'value': cellstr(now[0][0]) if now and now[0] else ''})

        if action == 'addrow':
            vals = data.get('values') or {}
            line = [str(vals.get(h, '')) if h else '' for h in hdr] or ['']
            res = wb.values_append(q(tws.title) + '!A1', params={'valueInputOption': 'USER_ENTERED', 'insertDataOption': 'INSERT_ROWS'},
                                   body={'values': [line]})
            rng = ((res or {}).get('updates') or {}).get('updatedRange', '')
            m = __import__('re').search(r'![A-Z]+(\d+)', rng)
            out({'ok': True, 'row': int(m.group(1)) if m else 0, 'values': line})

    out({'error': 'Unknown action'})

try:
    main()
except SystemExit:
    raise
except Exception as e:
    print(json.dumps({'error': f'{type(e).__name__}: {e}'}))
