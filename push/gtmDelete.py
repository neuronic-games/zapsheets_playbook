# gtmDelete.py — delete a T&MBoard row by Row ID
# Arg: {sheet_id}|{sheet_name}|{row_id}

import gspread
import sys, os, json, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

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
    print(json.dumps({"error": "Expected {sheet_id}|{sheet_name}|{row_id}"}))
    sys.exit(1)

sheet_id, sheet_name, row_id = parts
row_id = row_id.strip()

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
row_id_col = col.get('Ref Number', -1)

if row_id_col < 0:
    print(json.dumps({"error": "Sheet has no 'Ref Number' column"}))
    sys.exit(1)

target_row = None
for i, row in enumerate(all_values[1:], start=2):
    cell_val = row[row_id_col].strip() if row_id_col < len(row) else ''
    if cell_val == row_id:
        target_row = i
        break

if target_row is None:
    print(json.dumps({"error": f"Ref Number '{row_id}' not found in '{sheet_name}'"}))
    sys.exit(1)

try:
    ws.delete_rows(target_row)
    print(json.dumps({"ok": True, "deleted_row": target_row, "row_id": row_id}))
except Exception as e:
    print(json.dumps({"error": f"Could not delete row: {str(e)}"}))
    sys.exit(1)
