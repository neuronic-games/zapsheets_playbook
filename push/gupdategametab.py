# gupdategametab.py — update rows in a [GameName] tab.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: {
#   "game": "Lair",
#   "rows": [{"name": "MinPlayers", "value": "2", "extra": ""},
#             {"name": "Designer",   "value": "Jane", "extra": ""},
#             {"name": "Component",  "value": "50 Cards", "extra": ""},
#             ...]
# }
# Returns: {"ok": true, "rows_written": N} or {"error": "..."}
#
# Strategy: read all rows, update rows whose name appears in the payload,
# keeping all other rows unchanged. Multi-value fields (same name used
# multiple times) are replaced as a block. Names not yet in the sheet
# are appended.

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
    print(json.dumps({"error": "Could not authenticate: " + str(e)}))
    sys.exit(1)

arg = sys.argv[1] if len(sys.argv) > 1 else ''
if '|' not in arg:
    print(json.dumps({"error": "invalid argument — expected {sheet_id}|{base64_json}"}))
    sys.exit(1)

pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

game_name = data.get('game', '').strip()
if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game are required"}))
    sys.exit(1)

# ── Parse payload ──────────────────────────────────────────────────────────────
# Accept the new "rows" format: [{name, value, extra}, ...]
# Group by name so multi-value fields (Designer, Component, etc.) are handled
# as blocks. Preserve insertion order so the sheet is rebuilt cleanly.

rows_data = data.get('rows', [])

# new_values_by_name: name → list of [name, value, extra]
# Preserve the order in which names first appear.
name_order = []
new_values_by_name = {}
for row in rows_data:
    name  = str(row.get('name',  '')).strip()
    value = str(row.get('value', '')).strip()
    extra = str(row.get('extra', '')).strip()
    if name:
        if name not in new_values_by_name:
            name_order.append(name)
            new_values_by_name[name] = []
        new_values_by_name[name].append([name, value, extra])

# If nothing was sent, nothing to do.
if not new_values_by_name:
    print(json.dumps({"ok": True, "rows_written": 0}))
    sys.exit(0)

# ── Open sheet ─────────────────────────────────────────────────────────────────
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "Could not open spreadsheet: " + str(e)}))
    sys.exit(1)

bracketed = '[' + game_name + ']'
ws = None
for w in wb.worksheets():
    if w.title == bracketed or w.title.lower() == bracketed.lower():
        ws = w
        break
if ws is None:
    print(json.dumps({"error": "Tab '" + bracketed + "' not found"}))
    sys.exit(1)

try:
    all_rows = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": "Could not read tab: " + str(e)}))
    sys.exit(1)

# ── Rebuild rows ───────────────────────────────────────────────────────────────
# Walk the existing sheet. When we hit a name that's in our update map:
#   - first occurrence: emit all new rows for that name
#   - subsequent rows with the same name (old duplicates): skip them
# All other rows are kept unchanged.
new_rows      = []
written_names = set()

i = 0
while i < len(all_rows):
    row     = all_rows[i]
    padded  = (list(row) + ['', '', ''])[:3]
    row_name = padded[0].strip()

    if row_name and row_name in new_values_by_name:
        if row_name not in written_names:
            new_rows.extend(new_values_by_name[row_name])
            written_names.add(row_name)
        # Skip all consecutive rows with this name in the original sheet
        while i < len(all_rows) and (list(all_rows[i]) + [''])[:1][0].strip() == row_name:
            i += 1
    else:
        new_rows.append(padded)
        i += 1

# Append any names from the payload that weren't found in the sheet
for name in name_order:
    if name not in written_names:
        new_rows.append(['', '', ''])
        new_rows.extend(new_values_by_name[name])

# ── Write back ─────────────────────────────────────────────────────────────────
try:
    ws.clear()
    if new_rows:
        try:
            ws.update(range_name='A1', values=new_rows, value_input_option='USER_ENTERED')
        except TypeError:
            ws.update('A1', new_rows, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": "Could not write tab: " + str(e)}))
    sys.exit(1)

print(json.dumps({"ok": True, "rows_written": len(new_rows)}))
