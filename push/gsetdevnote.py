# gsetdevnote.py — set (or clear) the note on one observation row's Solutions / Thoughts cell
# in a [GameName] dev tab. Used by the DevBoard TODO list ("TODO" ↔ "DONE").
#
# Arg: {sheet_id}|{base64_json}
# JSON: { "tab": "[Game] dev", "row": 42, "obs": "...", "sol": "...", "note": "DONE" }
#   row  — sheet row number from the JSON cache (_row); verified against obs/sol,
#          and if the sheet has changed the row is found again by its text.
#   note — new note text; "" clears it.

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, base64, socket

socket.setdefaulttimeout(30)
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def out(d):
    print(json.dumps(d)); sys.exit(0)

def clean(v):
    return str(v if v is not None else '').strip().lstrip("'").strip()

try:
    raw = sys.argv[1]
    sheet_id, enc = raw.split('|', 1)
    data = json.loads(base64.b64decode(enc).decode('utf-8'))
except Exception as e:
    out({'error': f'Bad argument: {e}'})

tab  = data.get('tab', '')
row  = int(data.get('row') or 0)
obs  = clean(data.get('obs', ''))
sol  = clean(data.get('sol', ''))
note = str(data.get('note', '')).strip()

try:
    sa = gspread.service_account(filename=credFileName)
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    out({'error': f'Could not open spreadsheet: {e}'})

ws = next((w for w in wb.worksheets() if w.title.lower() == tab.lower()), None)
if ws is None:
    out({'error': f"Tab '{tab}' not found"})

try:
    try:
        values = ws.get_all_values(value_render_option='FORMULA')
    except TypeError:
        values = ws.get_all_values()
except Exception as e:
    out({'error': f'Could not read sheet: {e}'})
if not values:
    out({'error': 'Sheet is empty'})

headers = [h.strip() for h in values[0]]
def col(*names):
    return next((i for i, h in enumerate(headers) if h in names), -1)
obs_col = col('Observations', 'Observation')
sol_col = col('Solutions', 'Thoughts', 'Solution')
if sol_col < 0:
    out({'error': 'No Solutions / Thoughts column'})

def matches(r):  # r = 1-based sheet row
    if r < 2 or r > len(values): return False
    v = values[r - 1]
    o = clean(v[obs_col]) if 0 <= obs_col < len(v) else ''
    s = clean(v[sol_col]) if sol_col < len(v) else ''
    return o == obs and s == sol

target = row if matches(row) else next((r for r in range(2, len(values) + 1) if matches(r)), 0)
if not target:
    out({'error': 'That note was not found in the sheet — reload and try again'})

try:
    wb.batch_update({'requests': [{
        'updateCells': {
            'range': {'sheetId': ws.id, 'startRowIndex': target - 1, 'endRowIndex': target,
                      'startColumnIndex': sol_col, 'endColumnIndex': sol_col + 1},
            'rows': [{'values': [{'note': note}]}],
            'fields': 'note',
        }
    }]})
except Exception as e:
    out({'error': f'Could not save the note: {e}'})

out({'ok': True, 'row': target, 'note': note})
