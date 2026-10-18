# gappendtabrows.py — append new rows to a [GameName] tab without clearing it.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: {
#   "game": "Game Name",
#   "rows": [{"name": "ProductImage", "value": "https://…", "extra": ""}, …]
# }
# Returns: {"ok": true, "tab": "...", "rows_added": N} or {"error": "..."}

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1] if len(sys.argv) > 1 else ''
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

game_name = data.get('game', '').strip()
rows_in   = data.get('rows', [])

if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game name are required"}))
    sys.exit(1)

if not rows_in:
    print(json.dumps({"ok": True, "tab": "[" + game_name + "]", "rows_added": 0}))
    sys.exit(0)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws    = wb.worksheets()
bracketed = '[' + game_name + ']'

ws = next((w for w in all_ws if w.title == bracketed), None)
if ws is None:
    ws = next((w for w in all_ws if w.title.lower() == bracketed.lower()), None)
if ws is None:
    print(json.dumps({"error": "tab_not_found", "name": game_name}))
    sys.exit(1)

# Find the first empty row (append after last non-empty row)
try:
    existing = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read tab: {str(e)}"}))
    sys.exit(1)

# Find last row that has any content
last_row = 0
for i, row in enumerate(existing):
    if any(c.strip() for c in row):
        last_row = i + 1  # 1-based

start_row = last_row + 1  # append after last content row (skip one blank row)

new_sheet_rows = []
for r in rows_in:
    name  = str(r.get('name',  '') or '').strip()
    value = str(r.get('value', '') or '').strip()
    extra = str(r.get('extra', '') or '').strip()
    if name:
        new_sheet_rows.append([name, value, extra])

if not new_sheet_rows:
    print(json.dumps({"ok": True, "tab": ws.title, "rows_added": 0}))
    sys.exit(0)

# Ensure the sheet has enough rows
needed = start_row + len(new_sheet_rows)
if ws.row_count < needed:
    ws.add_rows(needed - ws.row_count + 5)

try:
    range_start = gspread.utils.rowcol_to_a1(start_row, 1)
    ws.update(new_sheet_rows, range_start, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not append rows: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": ws.title, "rows_added": len(new_sheet_rows)}))
