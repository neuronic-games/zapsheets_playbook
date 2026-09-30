# gdeletecontractrow.py — delete a row from the contracts sheet by its Ref Number.
#
# Arg:     "{sheet_id}|{base64_encoded_json}"
# JSON:    { "contract_id": "3KX7M2A9" }   ← Ref Number value
# Returns: {"ok": true, "deleted_row": 5}
#       or {"error": "..."}

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
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

raw = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
if '|' not in raw:
    print(json.dumps({"error": "Argument must be '{sheet_id}|{base64_json}'"}))
    sys.exit(1)

sheet_id, encoded = raw.split('|', 1)
sheet_id = sheet_id.strip()

try:
    data = json.loads(base64.b64decode(encoded).decode('utf-8'))
except Exception as e:
    print(json.dumps({"error": f"Could not decode payload: {str(e)}"}))
    sys.exit(1)

contract_id = str(data.get('contract_id', '')).strip()
if not sheet_id or not contract_id:
    print(json.dumps({"error": "sheet_id and contract_id are required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws = wb.worksheets()
ws = next((w for w in all_ws if w.title.lower() == 'contracts'), None)
if not ws:
    print(json.dumps({"error": "contracts sheet not found"}))
    sys.exit(1)

try:
    rows = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read contracts sheet: {str(e)}"}))
    sys.exit(1)

if not rows:
    print(json.dumps({"error": "contracts sheet is empty"}))
    sys.exit(1)

headers = [h.strip() for h in rows[0]]
try:
    id_col = headers.index('Ref Number')
except ValueError:
    print(json.dumps({"error": "Ref Number column not found in contracts sheet"}))
    sys.exit(1)

# Find the row index (1-based, row 1 = header)
row_index = None
for i, row in enumerate(rows[1:], start=2):  # start=2 because sheet rows are 1-indexed and row 1 is header
    cell = str(row[id_col]).strip() if id_col < len(row) else ''
    if cell == contract_id:
        row_index = i
        break

if row_index is None:
    # Already gone — treat as success
    print(json.dumps({"ok": True, "already_absent": True, "contract_id": contract_id}))
    sys.exit(0)

try:
    ws.delete_rows(row_index)
except Exception as e:
    print(json.dumps({"error": f"Could not delete row: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "deleted_row": row_index, "contract_id": contract_id}))
