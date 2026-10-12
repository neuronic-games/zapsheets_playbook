# gtmUpdate.py — update a T&MBoard row by Row ID
# Arg: {sheet_id}|{sheet_name}|{base64_json}
# JSON keys: row_id (required), plus any column name → new value pairs to update

import gspread
import sys, os, json, base64, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    s = (str(v) if v is not None else '').strip()
    return ("'" + s) if s else ''

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg   = sys.argv[1]
parts = arg.split('|', 2)
if len(parts) != 3:
    print(json.dumps({"error": "Expected {sheet_id}|{sheet_name}|{base64_json}"}))
    sys.exit(1)

sheet_id, sheet_name, json_b64 = parts
data = json.loads(base64.b64decode(json_b64).decode('utf-8'))

row_id = data.pop('row_id', '').strip()
if not row_id:
    print(json.dumps({"error": "row_id is required"}))
    sys.exit(1)

try:
    ss = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws = ss.worksheets()
ws = next((w for w in all_ws if w.title.lower() == sheet_name.lower()), None)
if ws is None:
    print(json.dumps({"error": f"Worksheet '{sheet_name}' not found"}))
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
row_id_col = col.get('Row ID', -1)

if row_id_col < 0:
    print(json.dumps({"error": "Sheet has no 'Row ID' column"}))
    sys.exit(1)

target_row = None
for i, row in enumerate(all_values[1:], start=2):
    cell_val = row[row_id_col].strip() if row_id_col < len(row) else ''
    if cell_val == row_id:
        target_row = i
        break

if target_row is None:
    print(json.dumps({"error": f"Row ID '{row_id}' not found"}))
    sys.exit(1)

updates = []
for field, value in data.items():
    idx = col.get(field, -1)
    if idx >= 0:
        updates.append({
            'range':  gspread.utils.rowcol_to_a1(target_row, idx + 1),
            'values': [[safe_str(value)]]
        })

if not updates:
    print(json.dumps({"error": "No matching columns to update"}))
    sys.exit(1)

try:
    ws.batch_update(updates, value_input_option='USER_ENTERED')
    print(json.dumps({"ok": True, "row": target_row, "row_id": row_id}))
except Exception as e:
    print(json.dumps({"error": f"Could not update cells: {str(e)}"}))
    sys.exit(1)
