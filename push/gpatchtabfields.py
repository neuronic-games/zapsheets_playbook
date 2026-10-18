# gpatchtabfields.py — update specific field rows in a [GameName] tab
# without clearing the rest of the tab.
#
# Finds each row where col A matches a field name and updates col B (and
# optionally col C) in-place. Rows not mentioned in the patch are untouched.
# If a field name appears more than once in the tab, only the FIRST row is
# patched (e.g. Designer — only Designer1 gets changed).
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: {
#   "game": "Game Name",
#   "fields": {"Description": "new text", "Price": "$29.99", ...}
# }
# Returns: {"ok": true, "tab": "...", "patched": N} or {"error": "..."}

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
fields    = data.get('fields', {})   # {fieldName: newValue, ...}

if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game name are required"}))
    sys.exit(1)

if not fields:
    print(json.dumps({"ok": True, "tab": "[" + game_name + "]", "patched": 0, "note": "no fields"}))
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
    # Tab doesn't exist yet — nothing to patch
    print(json.dumps({"ok": True, "tab": bracketed, "patched": 0, "note": "tab_not_found"}))
    sys.exit(0)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read tab: {str(e)}"}))
    sys.exit(1)

# Track which fields we still need to patch (case-sensitive first, then insensitive)
remaining = dict(fields)  # fieldName -> newValue
updates   = []            # gspread batch_update entries

for row_idx, row in enumerate(all_values, start=1):
    key = row[0].strip() if row else ''
    if not key:
        continue
    # Check for an exact match first
    if key in remaining:
        new_val = remaining.pop(key)
        a1 = gspread.utils.rowcol_to_a1(row_idx, 2)  # col B
        updates.append({'range': a1, 'values': [[new_val]]})
    else:
        # Case-insensitive fallback
        key_lower = key.lower()
        for fname in list(remaining.keys()):
            if fname.lower() == key_lower:
                new_val = remaining.pop(fname)
                a1 = gspread.utils.rowcol_to_a1(row_idx, 2)
                updates.append({'range': a1, 'values': [[new_val]]})
                break

if not updates:
    print(json.dumps({"ok": True, "tab": ws.title, "patched": 0,
                      "note": "no matching field rows found"}))
    sys.exit(0)

try:
    ws.batch_update(updates, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not patch tab: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": ws.title, "patched": len(updates)}))
