# greadkv.py — reads a key-value sheet where col A = key, col B = value.
#
# Unlike gread.py, this does NOT rely on the first row being a header row.
# It reads every non-empty row and treats col A as "Name" and col B as "Value",
# producing the same [{"Name": ..., "Value": ...}] format as settings.json.
#
# Rows where col A is empty, or where col A looks like a header label
# ("Name", "Key", "Label", "Setting"), are skipped automatically.
#
# Argument: "{sheetId}sheetname{tabName}"
# Example:  "1MIuMg...sheetnamesite"

import gspread
import sys
import os
import json

HEADER_LABELS = {'name', 'key', 'label', 'setting', 'field'}

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

arg = sys.argv[1] if len(sys.argv) > 1 else ''
if 'sheetname' not in arg:
    print(json.dumps({"error": "invalid argument — expected {sheetId}sheetname{tabName}"}))
    sys.exit(1)

sheet_id  = arg.split('sheetname')[0]
tab_name  = arg.split('sheetname')[1]

try:
    sa = gspread.service_account(filename=credFileName)
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "could not open sheet: " + str(e)}))
    sys.exit(1)

# Case-insensitive tab lookup
ws = next((w for w in wb.worksheets() if w.title == tab_name), None)
if ws is None:
    ws = next((w for w in wb.worksheets() if w.title.lower() == tab_name.lower()), None)
if ws is None:
    available = [w.title for w in wb.worksheets()]
    sys.stderr.write(json.dumps({"error": f"Tab '{tab_name}' not found. Available: {available}"}) + "\n")
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": "get_all_values failed: " + str(e)}))
    sys.exit(1)

records = []
for row in all_values:
    key  = row[0].strip() if len(row) > 0 else ''
    val  = row[1].strip() if len(row) > 1 else ''
    val1 = row[2].strip() if len(row) > 2 else ''
    val2 = row[3].strip() if len(row) > 3 else ''
    # Skip empty keys and header-like labels
    if not key or key.lower() in HEADER_LABELS:
        continue
    records.append({"Name": key, "Value": val, "Value 1": val1, "Value 2": val2})

json_data = json.dumps(records, ensure_ascii=False)

# Save to sheets/{id}/{tab}.json  (relative to cwd, same as gread.py)
out_path = os.path.join('..', 'sheets', sheet_id, tab_name.lower() + '.json')
os.makedirs(os.path.dirname(out_path), exist_ok=True)
with open(out_path, 'w', encoding='utf8') as f:
    f.write(json_data)

print(json_data)
