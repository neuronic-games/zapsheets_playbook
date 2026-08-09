# gupdategametab.py — overwrite a game's sheet tab with edited field data.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: { "game": "Game Name",
#         "rows": [{"name": "BggGameId", "value": "12345", "extra": ""}, …] }
#
# The tab is located as [Game Name] (or Game Name for legacy tabs).
# All existing content is replaced with the new rows.
# Returns: {"ok": true, "tab": "...", "rows": N} or {"error": "..."}

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

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws    = wb.worksheets()
bracketed = '[' + game_name + ']'

# Find the tab — try bracketed first, then plain name
ws = next((w for w in all_ws if w.title == bracketed), None)
if ws is None:
    ws = next((w for w in all_ws if w.title == game_name), None)
if ws is None:
    ws = next((w for w in all_ws if w.title.lower() == bracketed.lower()), None)
if ws is None:
    ws = next((w for w in all_ws if w.title.lower() == game_name.lower()), None)
if ws is None:
    print(json.dumps({"error": "tab_not_found", "name": game_name}))
    sys.exit(1)

# Build sheet rows — each dict becomes [name, value, extra]
sheet_rows = []
for r in rows_in:
    name  = str(r.get('name',  '') or '').strip()
    value = str(r.get('value', '') or '').strip()
    extra = str(r.get('extra', '') or '').strip()
    if name:
        sheet_rows.append([name, value, extra])

if not sheet_rows:
    print(json.dumps({"error": "No rows to write"}))
    sys.exit(1)

try:
    # Clear the tab then write fresh data
    ws.clear()
    ws.update(sheet_rows, 'A1', value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not write tab: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": ws.title, "rows": len(sheet_rows)}))
