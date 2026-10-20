# gaddgame.py — append a new row to the Games sheet.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: { name, tagline, status, count, duration, designer1, designer2, description }
# Returns: {"ok": true, "row": N} or {"error": "..."}

import gspread
import sys, os, json, base64, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": "Could not authenticate: " + str(e)}))
    sys.exit(1)

arg = sys.argv[1] if len(sys.argv) > 1 else ''
if '|' not in arg:
    print(json.dumps({"error": "invalid argument — expected {sheet_id}|{base64_json}"}))
    sys.exit(1)

pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

name        = data.get('name',        '').strip()
tagline     = data.get('tagline',     '').strip()
status      = data.get('status',      'Concept').strip()
count       = data.get('count',       '').strip()
duration    = data.get('duration',    '').strip()
designer1   = data.get('designer1',   '').strip()
designer2   = data.get('designer2',   '').strip()
description = data.get('description', '').strip()

if not name:
    print(json.dumps({"error": "name is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "Could not open spreadsheet: " + str(e)}))
    sys.exit(1)

# Find the Games worksheet
ws = next((w for w in wb.worksheets() if w.title.lower() == 'games'), None)
if ws is None:
    print(json.dumps({"error": "Games worksheet not found"}))
    sys.exit(1)

# Read headers from row 1
try:
    headers = ws.row_values(1)
except Exception as e:
    print(json.dumps({"error": "Could not read headers: " + str(e)}))
    sys.exit(1)

if not headers:
    print(json.dumps({"error": "Games sheet has no headers"}))
    sys.exit(1)

# Check for duplicate name
try:
    col_idx = next((i for i, h in enumerate(headers) if h.strip() == 'Name'), -1)
    if col_idx >= 0:
        col_letter = gspread.utils.rowcol_to_a1(1, col_idx + 1)[0]
        existing_names = ws.col_values(col_idx + 1)[1:]  # skip header
        for n in existing_names:
            if n.strip().lower() == name.lower():
                print(json.dumps({"error": "A game named '" + name + "' already exists."}))
                sys.exit(1)
except Exception:
    pass  # skip duplicate check on error

def safe_str(v):
    s = str(v).strip() if v else ''
    if not s: return ''
    if s.upper().startswith('=IMAGE('): return s
    return "'" + s

# Build field map: header → value
field_map = {
    'Name':        name,
    'Summary':     tagline,
    'Tagline':     tagline,
    'Tag Line':    tagline,
    'Status':      status,
    'Count':       count,
    'Duration':    duration,
    'Designer1':   designer1,
    'Designer 1':  designer1,
    'Designer2':   designer2,
    'Designer 2':  designer2,
    'Description': description,
}

# Build the new row aligned to headers
new_row = []
for h in headers:
    h_stripped = h.strip()
    val = field_map.get(h_stripped, '')
    new_row.append(safe_str(val) if val else '')

try:
    ws.append_row(new_row, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": "Could not append row: " + str(e)}))
    sys.exit(1)

new_row_num = len(ws.col_values(1))  # approximate
print(json.dumps({"ok": True, "row": new_row_num, "name": name}))
