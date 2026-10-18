# gupdategamecolumn.py — patch a single column in the Games tab for one game.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: {
#   "name":    "Game Name",          -- exact name to look up in col A
#   "column":  "Image URL",          -- column header (tries aliases too)
#   "value":   "=IMAGE(\"url\")"     -- value to write (USER_ENTERED)
# }
# Returns: {"ok": true, "row": N, "col": N} or {"error": "..."}

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

game_name   = data.get('name',   '').strip()
col_target  = data.get('column', '').strip()
new_value   = data.get('value',  '')

if not sheet_id or not game_name or not col_target:
    print(json.dumps({"error": "sheet_id, name, and column are all required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find the Games worksheet (first sheet, or one named Games/games)
ws = None
for candidate_name in ['Games', 'games', 'GAMES']:
    try:
        ws = wb.worksheet(candidate_name)
        break
    except gspread.exceptions.WorksheetNotFound:
        pass
if ws is None:
    ws = wb.get_worksheet(0)  # fall back to first sheet
if ws is None:
    print(json.dumps({"error": "Could not find Games worksheet"}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Sheet is empty"}))
    sys.exit(1)

headers = [h.strip() for h in all_values[0]]

# Column aliases (same as gupdategame.py)
ALIASES = {
    'Image URL': ('Image URL', 'ImageURL', 'Image'),
    'Cover URL': ('Cover URL', 'CoverURL', 'Cover'),
}

def find_col(target):
    """Return 0-based column index for target header name, checking aliases."""
    # Direct match
    for i, h in enumerate(headers):
        if h == target:
            return i
    # Case-insensitive
    for i, h in enumerate(headers):
        if h.lower() == target.lower():
            return i
    # Alias set
    aliases = ALIASES.get(target, (target,))
    for alias in aliases:
        for i, h in enumerate(headers):
            if h == alias or h.lower() == alias.lower():
                return i
    return -1

col_idx = find_col(col_target)
if col_idx < 0:
    print(json.dumps({"error": f"Column '{col_target}' not found in headers: {headers}"}))
    sys.exit(1)

# Find the game row (col A = name)
game_row_idx = -1
for i, row in enumerate(all_values[1:], start=1):  # skip header row
    cell_name = row[0].strip() if row else ''
    if cell_name == game_name:
        game_row_idx = i
        break
    if cell_name.lower() == game_name.lower():
        game_row_idx = i  # keep looking for exact match

if game_row_idx < 0:
    print(json.dumps({"error": f"Game '{game_name}' not found in sheet"}))
    sys.exit(1)

# 1-based row and col for gspread
sheet_row = game_row_idx + 1
sheet_col = col_idx + 1

# Safety: never write to the header row
if sheet_row <= 1:
    print(json.dumps({"error": "Computed target is in header row (row 1) — aborting to protect headers"}))
    sys.exit(1)

try:
    cell_a1 = gspread.utils.rowcol_to_a1(sheet_row, sheet_col)
    # Use explicit keyword args so gspread version differences don't swap arguments.
    ws.update(range_name=cell_a1, values=[[new_value]], value_input_option='USER_ENTERED')
except Exception as e:
    # Older gspread versions don't accept keyword args; fall back to positional (range first).
    try:
        ws.update(cell_a1, [[new_value]], value_input_option='USER_ENTERED')
    except Exception as e2:
        print(json.dumps({"error": "Could not update cell: " + str(e2)}))
        sys.exit(1)

print(json.dumps({"ok": True, "row": sheet_row, "col": sheet_col, "cell": cell_a1}))
