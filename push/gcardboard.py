# gcardboard.py — CardBoard: list a spreadsheet's tabs, and load / save card layouts.
#
# Arg: {sheet_id}|{base64_json}
# JSON: { "action": "tabs" | "layouts" | "save", ... }
#   tabs    — every visible tab except the "layouts" tab: [{title, rows, cols}]
#   layouts — {sheet title: layout} from the "layouts" tab
#   save    — sheet: tab title, layout: {...}  → one row per sheet in the "layouts" tab
#   tags    — {tags: [{tag, value}]} from the "tags" tab (tag names are stored with square brackets: [energy])
#   settag  — tag, value, old (previous name when renaming) → one row in the "tags" tab (created if missing)
#   deltag  — tag → removes its row
#   addfield — sheet: tab title, name: new column name → added after the last column heading
#   renamefield — sheet, old, name → the heading "old" becomes "name"
#   deleterow — sheet, row, values → deletes the row if it still holds those values
#   deletefield — sheet, field → deletes that column
#   savecells — sheet, cols [names], cells [{row, field, value, old}], adds [{key, values}],
#               delrows [{row, values}], delcols [names] → many edits in one go (deletions last)
#   grid    — sheet → {header, rows: [{row, values}]} as typed in the sheet (formulas, not results)
#   setcell — sheet, row, field, value, old → writes one cell (as if typed); refuses if the cell
#             no longer holds "old" (someone changed it in the meantime)
#   addrow  — sheet, values: {field: value} → appended after the last row
#   start   — tab (the one the browser opened last) → tabs, layouts and the first card's row of the
#             tab to show, all in one go so the designer appears quickly; the other rows load after
#   peek    — tab → the first card's row of that tab
# Writes use the plain Sheets API calls, which work the same with gspread 5 and 6.

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, base64, socket, datetime, warnings
warnings.filterwarnings('ignore')
socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
TAGS_TAB = 'tags'
TAGS_HEADERS = ['Tag', 'Value']
TAB     = 'layouts'   # CardBoard's own tab: one row per sheet's layout, plus settings
HEADERS = ['Sheet', 'Layout', 'Updated']

def out(d):
    print(json.dumps(d, ensure_ascii=False)); sys.exit(0)

def hidden(ws):
    # Hidden tabs in Google Sheets aren't offered (gspread 6 has isSheetHidden; 5.x keeps it in _properties)
    v = getattr(ws, 'isSheetHidden', None)
    if isinstance(v, bool): return v
    return bool(getattr(ws, '_properties', {}).get('hidden', False))

def q(title):
    return "'" + title.replace("'", "''") + "'"

def cell_text(cell):
    # The same text gread.py keeps for a cell: links as [text](url), =IMAGE() formulas kept
    text = cell.get('formattedValue', '')
    if not text:
        f = (cell.get('userEnteredValue') or {}).get('formulaValue', '')
        return f.strip() if f and f.strip().upper().startswith('=IMAGE(') else ''
    runs = cell.get('textFormatRuns', [])
    if runs and any(r.get('format', {}).get('link', {}).get('uri') for r in runs):
        res = ''
        for i, run in enumerate(runs):
            a = run.get('startIndex', 0)
            b = runs[i + 1].get('startIndex', len(text)) if i + 1 < len(runs) else len(text)
            chunk, uri = text[a:b], run.get('format', {}).get('link', {}).get('uri', '')
            res += f'[{chunk}]({uri})' if (uri and chunk) else chunk
        return res
    if cell.get('hyperlink'):
        return f'[{text}]({cell["hyperlink"]})'
    return text

def peek_rows(wb, title, n=8):
    # The heading row and the first rows of a tab, as records like gread.py's — only the
    # first n rows are fetched, so it's quick even on a big sheet
    rng = q(title) + '!1:' + str(n + 1)
    try:
        from google.auth.transport.requests import Request as GoogleAuthRequest
        import requests as requests_lib, urllib.parse
        auth = getattr(wb.client, 'auth', None) or wb.client.http_client.auth
        if not auth.valid: auth.refresh(GoogleAuthRequest())
        url = ('https://sheets.googleapis.com/v4/spreadsheets/' + urllib.parse.quote(wb.id, safe='') +
               '?includeGridData=true&ranges=' + urllib.parse.quote(rng, safe='') +
               '&fields=' + urllib.parse.quote('sheets.data.rowData.values(formattedValue,hyperlink,textFormatRuns,userEnteredValue)', safe=''))
        r = requests_lib.get(url, headers={'Authorization': f'Bearer {auth.token}'}, timeout=30)
        r.raise_for_status()
        rd = ((r.json().get('sheets') or [{}])[0].get('data') or [{}])[0].get('rowData', [])
        grid = [[cell_text(c) for c in row.get('values', [])] for row in rd]
    except Exception:
        grid = wb.values_get(rng).get('values', [])
    if not grid: return []
    hdr = [str(h).strip() for h in grid[0]]
    recs = []
    for row in grid[1:]:
        if not any(str(v).strip() for v in row): continue
        recs.append({h: (str(row[i]) if i < len(row) else '') for i, h in enumerate(hdr) if h})
    return recs

def read_layouts(wb, ws):
    if ws is None: return {}
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
    return layouts

def main():
    try:
        sheet_id, enc = sys.argv[1].split('|', 1)
        if enc == '-': enc = sys.stdin.read()   # big requests come in on stdin (a command-line argument is capped near 128 KB)
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
                      for w in sheets if w.title.lower() not in (TAB, TAGS_TAB) and not hidden(w)]})

    if action in ('tags', 'settag', 'deltag'):
        tws = next((w for w in sheets if w.title.lower() == TAGS_TAB), None)
        def brace(n):   # tag names are written [energy]; older rows saved as {energy} are read the same way
            n = str(n or '').strip().strip('{}[]').strip()
            return '[' + n + ']' if n else ''
        def rows_of(w):
            vals = wb.values_get(q(w.title), params={'valueRenderOption': 'FORMULA'}).get('values', [])
            return vals
        if action == 'tags':
            if tws is None: out({'ok': True, 'tags': []})
            vals = rows_of(tws); tags = []
            for i, r in enumerate(vals[1:]):
                t = brace(r[0] if r else '')
                if t: tags.append({'tag': t, 'value': str(r[1]) if len(r) > 1 else '', 'row': i + 2})
            out({'ok': True, 'tags': tags})
        tag = brace(data.get('tag'))
        if not tag: out({'error': 'Give the tag a name'})
        if tws is None:
            if action == 'deltag': out({'ok': True})
            tws = wb.add_worksheet(title=TAGS_TAB, rows=100, cols=len(TAGS_HEADERS))
            wb.values_update(q(TAGS_TAB) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [TAGS_HEADERS]})
            try:
                wb.batch_update({'requests': [
                    {'updateSheetProperties': {'properties': {'sheetId': tws.id, 'gridProperties': {'frozenRowCount': 1}}, 'fields': 'gridProperties.frozenRowCount'}},
                    {'repeatCell': {'range': {'sheetId': tws.id, 'startRowIndex': 0, 'endRowIndex': 1, 'startColumnIndex': 0, 'endColumnIndex': 2},
                                    'cell': {'userEnteredFormat': {'backgroundColor': {'red': .23, 'green': .2, 'blue': .45},
                                             'textFormat': {'bold': True, 'foregroundColor': {'red': 1, 'green': 1, 'blue': 1}}}},
                                    'fields': 'userEnteredFormat(backgroundColor,textFormat)'}},
                    {'updateDimensionProperties': {'range': {'sheetId': tws.id, 'dimension': 'COLUMNS', 'startIndex': 0, 'endIndex': 1},
                                                   'properties': {'pixelSize': 160}, 'fields': 'pixelSize'}},
                    {'updateDimensionProperties': {'range': {'sheetId': tws.id, 'dimension': 'COLUMNS', 'startIndex': 1, 'endIndex': 2},
                                                   'properties': {'pixelSize': 460}, 'fields': 'pixelSize'}},
                ]})
            except Exception:
                pass
        vals = rows_of(tws)
        def find(t):
            return next((i + 1 for i, r in enumerate(vals) if i > 0 and r and brace(r[0]).lower() == t.lower()), 0)
        if action == 'deltag':
            row = find(tag)
            if row:
                wb.batch_update({'requests': [{'deleteDimension': {'range': {'sheetId': tws.id, 'dimension': 'ROWS', 'startIndex': row - 1, 'endIndex': row}}}]})
            out({'ok': True})
        # settag: a new name must not clash with another tag; renaming keeps the row
        # Without "old" this updates the tag's row (or adds one). Renaming (old given, different
        # name) keeps the old row, and refuses a name another tag already has.
        old = brace(data.get('old'))
        other = find(tag)
        if old and old.lower() != tag.lower():
            row = find(old)
            if other and other != row:
                out({'error': f'There is already a tag called {tag}'})
            row = row or other
        else:
            row = other
        value = str(data.get('value', ''))
        if row:
            wb.values_update(q(tws.title) + '!A' + str(row) + ':B' + str(row), params={'valueInputOption': 'RAW'}, body={'values': [[tag, value]]})
        else:
            res = wb.values_append(q(tws.title) + '!A1', params={'valueInputOption': 'RAW', 'insertDataOption': 'INSERT_ROWS'}, body={'values': [[tag, value]]})
        out({'ok': True, 'tag': tag, 'value': value})

    ws = next((w for w in sheets if w.title.lower() == TAB), None)

    if action == 'layouts':
        out({'ok': True, 'layouts': read_layouts(wb, ws)})

    if action == 'start':
        tabs = [{'title': w.title, 'rows': w.row_count, 'cols': w.col_count}
                for w in sheets if w.title.lower() not in (TAB, TAGS_TAB) and not hidden(w)]
        layouts = read_layouts(wb, ws)
        want = str(data.get('tab') or '')
        titles = [t['title'] for t in tabs]
        tab = want if want in titles else next((t for t in titles if t in layouts), titles[0] if titles else '')
        out({'ok': True, 'title': wb.title, 'tabs': tabs, 'layouts': layouts, 'tab': tab,
             'rows': peek_rows(wb, tab) if tab else []})

    if action == 'peek':
        tab = str(data.get('tab') or '')
        w = next((x for x in sheets if x.title == tab), None)
        if w is None: out({'error': 'No tab called ' + tab})
        out({'ok': True, 'rows': peek_rows(wb, tab)})

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

    if action == 'renamefield':
        name = str(data.get('sheet', '')).strip()
        old = str(data.get('old', '')).strip()
        field = str(data.get('name', '')).strip()
        tws = next((w for w in sheets if w.title == name), None)
        if tws is None or name.lower() == TAB:
            out({'error': 'That sheet was not found'})
        if not field or not old:
            out({'error': 'Give the column a name'})
        hdr = (wb.values_get(q(tws.title) + '!1:1').get('values') or [[]])[0]
        idx = next((i for i, h in enumerate(hdr) if str(h).strip() == old), -1)
        if idx < 0:
            out({'error': f'The column "{old}" wasn\'t found — Fetch to see the latest'})
        if any(i != idx and str(h).strip().lower() == field.lower() for i, h in enumerate(hdr)):
            out({'error': f'There is already a column called "{field}"'})
        a1 = gspread.utils.rowcol_to_a1(1, idx + 1)
        wb.values_update(q(tws.title) + '!' + a1, params={'valueInputOption': 'RAW'}, body={'values': [[field]]})
        out({'ok': True, 'old': old, 'field': field, 'column': a1.rstrip('0123456789')})

    if action in ('grid', 'setcell', 'addrow', 'deleterow', 'deletefield', 'savecells'):
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

        if action == 'grid':
            # One call for both: what's in each cell (formulas as written, numbers unformatted — what
            # editing shows) and what the sheet displays (formula results, number and date formats).
            # Each row's 'values' are the raw values; 'shown' maps a column to [raw, displayed] where they differ.
            def raw_of(c):
                uv = c.get('userEnteredValue') or {}
                fmt = ((c.get('effectiveFormat') or {}).get('numberFormat') or {}).get('type', '')
                if 'formulaValue' in uv: return uv['formulaValue']
                if 'stringValue' in uv: return uv['stringValue']
                if 'boolValue' in uv: return 'TRUE' if uv['boolValue'] else 'FALSE'
                if 'numberValue' in uv:
                    if fmt in ('DATE', 'TIME', 'DATE_TIME'): return c.get('formattedValue', '')
                    return cellstr(uv['numberValue'])
                return ''
            grid = None
            try:
                from google.auth.transport.requests import Request as GoogleAuthRequest
                import requests as requests_lib, urllib.parse
                auth = getattr(wb.client, 'auth', None) or wb.client.http_client.auth
                if not auth.valid: auth.refresh(GoogleAuthRequest())
                url = ('https://sheets.googleapis.com/v4/spreadsheets/' + urllib.parse.quote(wb.id, safe='') +
                       '?includeGridData=true&ranges=' + urllib.parse.quote(q(tws.title), safe='') +
                       '&fields=' + urllib.parse.quote('sheets.data(startRow,rowData.values(formattedValue,userEnteredValue,effectiveFormat.numberFormat.type))', safe=''))
                rq = requests_lib.get(url, headers={'Authorization': f'Bearer {auth.token}'}, timeout=60)
                rq.raise_for_status()
                rd = ((rq.json().get('sheets') or [{}])[0].get('data') or [{}])[0].get('rowData', [])
                grid = [[(raw_of(c), cellstr(c.get('formattedValue', ''))) for c in row.get('values', [])] for row in rd]
            except Exception:
                grid = None
            if grid is None:   # the API call failed: two plain reads instead
                fv = read('1:' + str(tws.row_count), False); rv = read('1:' + str(tws.row_count))
                grid = []
                for i in range(max(len(fv), len(rv))):
                    a = fv[i] if i < len(fv) else []; b = rv[i] if i < len(rv) else []
                    grid.append([(cellstr(b[j]) if j < len(b) else '', cellstr(a[j]) if j < len(a) else '') for j in range(max(len(a), len(b)))])
            hdr = [d.strip() for _, d in (grid[0] if grid else [])]
            while hdr and not hdr[-1]: hdr.pop()
            rows = []
            for i, cells in enumerate(grid[1:]):
                cells = cells[:len(hdr)]
                r = [c[0] for c in cells]
                if any(x.strip() for x in r):
                    row = {'row': i + 2, 'values': r + [''] * (len(hdr) - len(r))}
                    diff = {str(j): [c[0], c[1]] for j, c in enumerate(cells) if c[0] != c[1] and c[0] != ''}
                    if diff: row['shown'] = diff
                    rows.append(row)
            out({'ok': True, 'header': hdr, 'rows': rows})

        hdr = [cellstr(h).strip() for h in ((read('1:1', False) or [[]])[0])]

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

        if action == 'savecells':
            # Many edits in one go: cells [{row, field, value, old}] and new rows adds [{key, values}].
            # Each cell is written only if it still holds "old"; otherwise it's a conflict and the sheet's value stays.
            cells = data.get('cells') or []
            adds = data.get('adds') or []
            # New columns first: their headings go after the last heading, in order
            cols_added = []
            newcols = [str(c).strip() for c in (data.get('cols') or []) if str(c).strip()]
            have = [h.lower() for h in hdr]
            newcols = [c for i, c in enumerate(newcols) if c.lower() not in have and c.lower() not in [x.lower() for x in newcols[:i]]]
            if newcols:
                at = len(hdr)
                while at > 0 and not hdr[at - 1]: at -= 1
                if at + len(newcols) > tws.col_count: tws.add_cols(at + len(newcols) - tws.col_count)
                wb.values_update(q(tws.title) + '!' + gspread.utils.rowcol_to_a1(1, at + 1), params={'valueInputOption': 'RAW'}, body={'values': [newcols]})
                hdr = hdr[:at] + newcols
                cols_added = newcols
            results, todo = [], []
            for c in cells:
                field = str(c.get('field', ''))
                try: row = int(c.get('row', 0))
                except Exception: row = 0
                if row < 2 or field not in hdr:
                    results.append({'row': row, 'field': field, 'status': 'missing'}); continue
                todo.append((c, row, field, q(tws.title) + '!' + gspread.utils.rowcol_to_a1(row, hdr.index(field) + 1)))
            def batch_get(ranges):
                if not ranges: return []
                got = wb.values_batch_get(ranges, params={'valueRenderOption': 'FORMULA', 'dateTimeRenderOption': 'FORMATTED_STRING'})
                vr = got.get('valueRanges', [])
                return [cellstr(v['values'][0][0]) if v.get('values') and v['values'][0] else '' for v in vr]
            writes, written = [], []
            for (c, row, field, a1), cur in zip(todo, batch_get([t[3] for t in todo])):
                if cur != str(c.get('old') or ''):
                    results.append({'row': row, 'field': field, 'status': 'conflict', 'current': cur}); continue
                writes.append({'range': a1, 'values': [[str(c.get('value', ''))]]}); written.append((row, field, a1))
            if writes:
                wb.values_batch_update({'valueInputOption': 'USER_ENTERED', 'data': writes})
                for (row, field, a1), now in zip(written, batch_get([w[2] for w in written])):
                    results.append({'row': row, 'field': field, 'status': 'ok', 'value': now})
            added = []
            lines = [[str((a.get('values') or {}).get(h, '')) if h else '' for h in hdr] or [''] for a in adds]
            if lines:
                res = wb.values_append(q(tws.title) + '!A1', params={'valueInputOption': 'USER_ENTERED', 'insertDataOption': 'INSERT_ROWS'}, body={'values': lines})
                rng = ((res or {}).get('updates') or {}).get('updatedRange', '')
                m = __import__('re').search(r'![A-Z]+(\d+)', rng)
                first = int(m.group(1)) if m else 0
                added = [{'key': a.get('key'), 'row': (first + i) if first else 0} for i, a in enumerate(adds)]
            # Deletions last, so the row numbers above stay right: rows (only if they still hold what the
            # browser showed), then columns, each from the bottom/right up so the others don't move first
            reqs, deleted_rows, deleted_cols, row_conflicts = [], [], [], []
            dels = []
            for d in (data.get('delrows') or []):
                try: dels.append((int(d.get('row', 0)), [str(v) for v in (d.get('values') or [])]))
                except Exception: pass
            dels = sorted({r: v for r, v in dels if r >= 2}.items(), reverse=True)
            if dels:
                width = max([len(hdr)] + [len(v) for _, v in dels]) or 1
                curs = wb.values_batch_get(['A%d:%s' % (r, gspread.utils.rowcol_to_a1(r, width)) for r, _ in dels], params={'valueRenderOption': 'FORMULA', 'dateTimeRenderOption': 'FORMATTED_STRING'}).get('valueRanges', [])
                for (r, want), vr in zip(dels, curs):
                    cur = [cellstr(x) for x in ((vr.get('values') or [[]])[0])]
                    n = max(len(cur), len(want))
                    if (cur + [''] * (n - len(cur))) != (want + [''] * (n - len(want))):
                        row_conflicts.append(r); continue
                    reqs.append({'deleteDimension': {'range': {'sheetId': tws.id, 'dimension': 'ROWS', 'startIndex': r - 1, 'endIndex': r}}})
                    deleted_rows.append(r)
            for i in sorted({hdr.index(str(c)) for c in (data.get('delcols') or []) if str(c) in hdr}, reverse=True):
                reqs.append({'deleteDimension': {'range': {'sheetId': tws.id, 'dimension': 'COLUMNS', 'startIndex': i, 'endIndex': i + 1}}})
                deleted_cols.append(hdr[i])
            if reqs:
                wb.batch_update({'requests': reqs})
                for a in added:   # new rows sit below the deleted ones, so they move up
                    if a['row']: a['row'] -= len([r for r in deleted_rows if r < a['row']])
            out({'ok': True, 'results': results, 'added': added, 'cols': cols_added,
                 'deleted_rows': deleted_rows, 'deleted_cols': deleted_cols, 'row_conflicts': row_conflicts})

        def delete_dim(dim, i):   # i: 0-based row or column index
            wb.batch_update({'requests': [{'deleteDimension': {'range': {'sheetId': tws.id, 'dimension': dim, 'startIndex': i, 'endIndex': i + 1}}}]})

        if action == 'deleterow':
            try: row = int(data.get('row', 0))
            except Exception: row = 0
            if row < 2: out({'error': 'That row was not found — reload the data'})
            # Only if the row still holds what the browser showed (rows may have moved since)
            want = [str(v) for v in (data.get('values') or [])]
            cur = read('A' + str(row) + ':' + gspread.utils.rowcol_to_a1(row, max(1, len(hdr), len(want))))
            cur = [cellstr(v) for v in (cur[0] if cur else [])]
            n = max(len(cur), len(want))
            if (cur + [''] * (n - len(cur))) != (want + [''] * (n - len(want))):
                out({'error': 'conflict'})
            delete_dim('ROWS', row - 1)
            out({'ok': True, 'row': row})

        if action == 'deletefield':
            field = str(data.get('field', '')).strip()
            if not field or field not in hdr:
                out({'error': f'The column "{field}" wasn\'t found — Fetch to see the latest'})
            delete_dim('COLUMNS', hdr.index(field))
            out({'ok': True, 'field': field})

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
