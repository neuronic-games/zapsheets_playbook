# gsetgamepageurl.py — write Page URL for a game row (only if currently empty)
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: orig_name, page_url

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

# Parse arg: {sheet_id}|{base64_json}
arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

orig_name = data.get('orig_name', '').strip()
page_url  = data.get('page_url',  '').strip()

if not orig_name:
    print(json.dumps({"error": "Missing orig_name"}))
    sys.exit(1)

if not page_url:
    print(json.dumps({"error": "Missing page_url"}))
    sys.exit(1)

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find games worksheet (case-insensitive)
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == 'games'), None)
if ws is None:
    print(json.dumps({"error": "Games worksheet not found"}))
    sys.exit(1)

# Read all values
try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Games sheet is empty"}))
    sys.exit(1)

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def find_col(*variants):
    for v in variants:
        if v in col:
            return col[v]
    return -1

# Find the game row
name_col = find_col('Name')
if name_col < 0:
    print(json.dumps({"error": "No 'Name' column found"}))
    sys.exit(1)

target_row = None
for i, row in enumerate(all_values[1:], start=2):
    cell_name = row[name_col].strip() if name_col < len(row) else ''
    if cell_name == orig_name:
        target_row = i
        break

if target_row is None:
    print(json.dumps({"error": f"Game '{orig_name}' not found"}))
    sys.exit(1)

# Find Page URL column
page_col = find_col('Page URL', 'PageURL', 'Page')
if page_col < 0:
    print(json.dumps({"error": "No 'Page URL' column found in games sheet"}))
    sys.exit(1)

# Read the existing value — only write if empty
existing_row = all_values[target_row - 1]  # all_values is 0-based
existing_val = existing_row[page_col].strip() if page_col < len(existing_row) else ''
if existing_val:
    print(json.dumps({"ok": True, "skipped": True, "reason": "Page URL already set"}))
    sys.exit(0)

# Write the new URL
cell_ref = gspread.utils.rowcol_to_a1(target_row, page_col + 1)
try:
    ws.batch_update(
        [{'range': cell_ref, 'values': [[page_url]]}],
        value_input_option='USER_ENTERED'
    )
    print(json.dumps({"ok": True, "row": target_row, "cell": cell_ref}))
except Exception as e:
    print(json.dumps({"error": f"Could not update cell: {str(e)}"}))
    sys.exit(1)
