# grename_designer.py — rename a designer in all affected rows of the Games sheet
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: old_name, new_name

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

old_name = data.get('old_name', '').strip()
new_name = data.get('new_name', '').strip()

if not old_name or not new_name:
    print(json.dumps({"error": "old_name and new_name are required"}))
    sys.exit(1)

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find Games worksheet
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == 'games'), None)
if ws is None:
    print(json.dumps({"error": "Games worksheet not found"}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"ok": True, "updated": 0}))
    sys.exit(0)

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

# Find all Designer columns (Designer1–4, with or without space)
designer_cols = []
for variant in ['Designer1','Designer 1','Designer2','Designer 2',
                'Designer3','Designer 3','Designer4','Designer 4']:
    if variant in col:
        designer_cols.append(col[variant])
# Deduplicate while preserving order
seen = set()
designer_cols = [c for c in designer_cols if not (c in seen or seen.add(c))]

if not designer_cols:
    print(json.dumps({"error": "No Designer columns found in Games sheet"}))
    sys.exit(1)

# Scan all data rows and collect cells that need updating
updates = []
for i, row in enumerate(all_values[1:], start=2):
    for ci in designer_cols:
        val = row[ci].strip() if ci < len(row) else ''
        if val == old_name:
            updates.append({
                'range':  gspread.utils.rowcol_to_a1(i, ci + 1),
                'values': [[new_name]]
            })

if not updates:
    print(json.dumps({"ok": True, "updated": 0}))
    sys.exit(0)

try:
    ws.batch_update(updates, value_input_option='USER_ENTERED')
    print(json.dumps({"ok": True, "updated": len(updates)}))
except Exception as e:
    print(json.dumps({"error": f"Could not update cells: {str(e)}"}))
    sys.exit(1)
