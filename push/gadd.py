# gadd.py — append a row to a Google Sheet worksheet
# Arg: {sheet_id}|{base64_encoded_json_row}

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets formula interpretation."""
    s = (str(v) if v is not None else '').strip()
    return ("'" + s) if s else ''

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

# Find the target worksheet (case-insensitive) — no silent fallback
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == sheet_name.lower()), None)
if ws is None:
    available = ', '.join(w.title for w in all_worksheets)
    print(json.dumps({"error": f"Worksheet '{sheet_name}' not found. Available tabs: {available}"}))
    sys.exit(1)

# Read all values to get headers and determine next available row
try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Sheet is empty — no header row found"}))
    sys.exit(1)

headers = all_values[0]

# Find the last row that has any non-empty cell value.
# Using len(all_values) directly would include trailing empty rows left behind
# when data was cleared (deleted cell values but row structure kept), causing gaps.
last_data_row = 1  # header is at minimum row 1
for i, row in enumerate(all_values, start=1):
    if any(cell.strip() for cell in row):
        last_data_row = i
next_row = last_data_row + 1

# Build row list aligned to headers
new_row = [safe_str(row_data.get(h.strip(), '')) for h in headers]

if not headers:
    print(json.dumps({"error": "Header row is empty"}))
    sys.exit(1)

# Use ws.update() (direct cell write, same API path as batch_update) instead of
# append_row (INSERT_ROWS) so this works even on sheets with row-insertion restrictions.
try:
    end_cell  = gspread.utils.rowcol_to_a1(next_row, len(new_row))
    range_str = f'A{next_row}:{end_cell}'
    ws.update(range_name=range_str, values=[new_row], value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not write row: {str(e)}"}))
    sys.exit(1)

non_empty = sum(1 for v in new_row if v)
print(json.dumps({
    "ok": True,
    "sheet": ws.title,
    "row": next_row,
    "headers": headers,
    "written": new_row,
    "non_empty_fields": non_empty
}))
