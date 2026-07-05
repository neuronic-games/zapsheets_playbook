# gadd.py — append a row to a Google Sheet worksheet
# Arg: {sheet_id}|{base64_encoded_json_row}

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

mServiceAccount = gspread.service_account(filename=credFileName)

# Parse arg: {sheet_id}|{base64_json}  OR  {sheet_id}|{sheet_name}|{base64_json}
arg   = sys.argv[1]
parts = arg.split('|', 2)
if len(parts) == 3:
    sheet_id, sheet_name, json_b64 = parts
else:
    sheet_id  = parts[0]
    sheet_name = 'pitches'
    json_b64  = parts[1]

row_data = json.loads(base64.b64decode(json_b64).decode('utf-8'))

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find the target worksheet (case-insensitive), fall back to first sheet
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == sheet_name.lower()), None)
if ws is None:
    ws = all_worksheets[0] if all_worksheets else None
if ws is None:
    print(json.dumps({"error": "No worksheets found in spreadsheet"}))
    sys.exit(1)

# Read headers from row 1
try:
    headers = ws.row_values(1)
except Exception as e:
    print(json.dumps({"error": f"Could not read headers: {str(e)}"}))
    sys.exit(1)

# Build row list aligned to headers
new_row = [row_data.get(h.strip(), '') for h in headers]

# Append the row
try:
    ws.append_row(new_row, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not append row: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True}))
