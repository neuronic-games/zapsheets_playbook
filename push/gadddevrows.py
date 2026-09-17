# gadddevrows.py — batch-append all rows for one session to a [Game] dev tab.
# Called in the background by addDevRows.php; no response is read.
#
# Arg: {sheet_id}|{base64_encoded_json}
# JSON: { "tab": "[GameName] dev", "rows": [ {Date, Event, People, Observations, Thoughts}, ... ] }

import gspread
import sys, os, json, base64, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    s = (str(v) if v is not None else '').strip()
    if not s:
        return ''
    if s.upper().startswith('=IMAGE('):
        return s
    return "'" + s

if not os.path.exists(credFileName):
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception:
    sys.exit(1)

arg      = sys.argv[1] if len(sys.argv) > 1 else ''
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

tab_name = data.get('tab', '')
rows     = data.get('rows', [])

if not tab_name or not rows:
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception:
    sys.exit(1)

all_ws = wb.worksheets()
ws = next((w for w in all_ws if w.title.lower() == tab_name.lower()), None)
if ws is None:
    sys.exit(1)

try:
    existing = ws.get_all_values()
except Exception:
    sys.exit(1)

if not existing:
    sys.exit(1)

headers = existing[0]

def build_row(row_obj):
    return [safe_str(row_obj.get(h.strip(), '')) for h in headers]

sheet_rows = [build_row(r) for r in rows]

try:
    ws.append_rows(
        sheet_rows,
        value_input_option='USER_ENTERED',
        insert_data_option='OVERWRITE',
        table_range='A1',
    )
except Exception:
    sys.exit(1)
