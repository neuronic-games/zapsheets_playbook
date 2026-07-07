# gpublishgame.py — publish a game tab as game-en.json for the /view page.
#
# Sheet format (no header row):
#   Col A  = field name  (e.g. "Title", "Designer", "ProductImage")
#   Col B+ = values      (one or more; extra columns hold additional values)
#
# Each row produces one JSON record:
#   {"Name": key, "Value": col_B, "Value 1": col_C, "Value 2": col_D, …}
# Extra value columns are stored as "Value 1", "Value 2", etc.
# Multi-row fields (e.g. multiple Designers) appear as separate records.
#
# Argument:  "{sheet_id}|{game_name}"
# Returns (stdout): JSON — {"ok": true, "tab": "...", "records": N}
#                       or {"error": "tab_not_found", "name": "..."}
#                       or {"error": "..."}   for any other failure

import gspread
import sys, os, json

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

arg = sys.argv[1] if len(sys.argv) > 1 else ''
if '|' not in arg:
    print(json.dumps({"error": "invalid argument — expected {sheet_id}|{game_name}"}))
    sys.exit(1)

sheet_id, game_name = arg.split('|', 1)
game_name = game_name.strip()

if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game_name must not be empty"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "could not open sheet: " + str(e)}))
    sys.exit(1)

all_worksheets = wb.worksheets()

# Exact match first, then case-insensitive
ws = next((w for w in all_worksheets if w.title == game_name), None)
if ws is None:
    ws = next((w for w in all_worksheets if w.title.lower() == game_name.lower()), None)
if ws is None:
    print(json.dumps({"error": "tab_not_found", "name": game_name}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": "get_all_values failed: " + str(e)}))
    sys.exit(1)

records = []
for row in all_values:
    key = row[0].strip() if len(row) > 0 else ''
    if not key:
        continue  # skip blank rows

    # Trim trailing empty cells; keep internal structure intact.
    vals = [c.strip() for c in row[1:]]
    while vals and not vals[-1]:
        vals.pop()

    record = {"Name": key, "Value": vals[0] if vals else ""}
    for i, v in enumerate(vals[1:], 1):
        record["Value " + str(i)] = v
    records.append(record)

json_data = json.dumps(records, ensure_ascii=False)

# Build a filesystem-safe version of the game name (only '/' is truly invalid on Linux)
safe_name = game_name.replace('/', '-').replace('\\', '-')

# Write to ../sheets/{sheet_id}/game-{game_name}-en.json
out_dir  = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', sheet_id)
out_path = os.path.join(out_dir, 'game-' + safe_name + '-en.json')
try:
    os.makedirs(out_dir, exist_ok=True)
    with open(out_path, 'w', encoding='utf8') as f:
        f.write(json_data)
except Exception as e:
    print(json.dumps({"error": "file write failed: " + str(e)}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": ws.title, "records": len(records)}))
