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

sheet_id, sheet_name, payload = parts
# payload is either a plain row_id string, or base64-encoded JSON for fingerprint-based delete
import base64, hashlib, time as _time
try:
    decoded = json.loads(base64.b64decode(payload).decode('utf-8'))
    row_id      = decoded.get('row_id', '').strip()
    fingerprint = decoded.get('_fingerprint', None)
except Exception:
    row_id      = payload.strip()
    fingerprint = None

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

if row_id:
    # Normal path: find by Ref Number
    for i, row in enumerate(all_values[1:], start=2):
        cell_val = row[row_id_col].strip() if row_id_col < len(row) else ''
        if cell_val == row_id:
            target_row = i
            break
    if target_row is None:
        print(json.dumps({"error": f"Ref Number '{row_id}' not found in '{sheet_name}'"}))
        sys.exit(1)
elif fingerprint:
    # No Ref Number: locate by fingerprint match on rows that have no Ref Number
    fp = {k: str(v).strip().lower() for k, v in fingerprint.items() if str(v).strip()}
    for i, row in enumerate(all_values[1:], start=2):
        cell = row[row_id_col].strip() if row_id_col < len(row) else ''
        if cell:
            continue  # skip rows that already have a Ref Number
        match = True
        for fname, fval in fp.items():
            fidx = col.get(fname, -1)
            if fidx < 0:
                continue
            cv = row[fidx].strip().lower() if fidx < len(row) else ''
            if cv != fval:
                match = False
                break
        if match:
            target_row = i
            break
    if target_row is None:
        print(json.dumps({"error": "Could not locate row by fingerprint — no blank-Ref row matches"}))
        sys.exit(1)
else:
    print(json.dumps({"error": "row_id is required"}))
    sys.exit(1)

try:
    ws.delete_rows(target_row)
    print(json.dumps({"ok": True, "deleted_row": target_row, "row_id": row_id}))
except Exception as e:
    print(json.dumps({"error": f"Could not delete row: {str(e)}"}))
    sys.exit(1)
