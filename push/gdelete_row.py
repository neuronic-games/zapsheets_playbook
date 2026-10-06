# gdelete_row.py — delete a matching row from the Pitches sheet
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: game, publisher, contact, date, event

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    mServiceAccount = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

game      = data.get('game',      '').strip()
publisher = data.get('publisher', '').strip()
contact   = data.get('contact',   '').strip()
date      = data.get('date',      '').strip()
event     = data.get('event',     '').strip()
status    = data.get('status',    '').strip()

try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == 'pitches'), None)
if ws is None:
    print(json.dumps({"error": "Pitches worksheet not found"}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Sheet is empty"}))
    sys.exit(1)

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def cell(row, field):
    idx = col.get(field, -1)
    return row[idx].strip() if 0 <= idx < len(row) else ''

import re as _re
def _strip_md_link(s):
    """Convert [text](url) markdown (from gread.py hyperlink encoding) to plain text/url."""
    m = _re.match(r'^\[([^\]]*)\]\(([^)]*)\)$', s)
    return m.group(1) if m else s

# Normalise incoming values: strip any [text](url) markdown the client may
# have read from the JSON cache (gread.py encodes hyperlinks that way).
def norm(s):
    return _strip_md_link(s.strip())

game      = norm(game)
publisher = norm(publisher)
contact   = norm(contact)
event     = norm(event)
status    = norm(status)
date      = date.strip()

target_sheet_row = None
for i, row in enumerate(all_values[1:], start=2):
    if (cell(row, 'Game')      == game      and
        cell(row, 'Publisher') == publisher and
        (cell(row, 'Contact')  == contact or norm(cell(row, 'Contact')) == contact) and
        cell(row, 'Date')      == date      and
        cell(row, 'Event')     == event     and
        cell(row, 'Status')    == status):
        target_sheet_row = i
        break

if target_sheet_row is None:
    print(json.dumps({"error": "Matching row not found in sheet"}))
    sys.exit(1)

try:
    ws.delete_rows(target_sheet_row)
    print(json.dumps({"ok": True, "deleted_row": target_sheet_row}))
except Exception as e:
    print(json.dumps({"error": f"Could not delete row: {str(e)}"}))
    sys.exit(1)
