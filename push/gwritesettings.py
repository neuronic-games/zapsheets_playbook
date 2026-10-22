# gwritesettings.py — write flat key-value pairs to the Settings tab in Google Sheets.
#
# Reads an existing Settings tab and updates rows in place (matching col A = Name),
# or appends new rows for keys not yet present. Preserves all existing rows.
#
# Argument: "{sheetId}|{base64_json}"
# where base64_json decodes to a flat dict: {"Company": "UP Games", "Logo URL": "..."}
#
# Only keys present in the posted dict are written — others are left untouched.

import gspread
import sys
import os
import json
import base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({'error': 'credentials.json not found'}))
    sys.exit(1)

arg = sys.argv[1] if len(sys.argv) > 1 else ''
if '|' not in arg:
    print(json.dumps({'error': 'invalid argument — expected {sheetId}|{base64_json}'}))
    sys.exit(1)

sheet_id, encoded = arg.split('|', 1)
try:
    data = json.loads(base64.b64decode(encoded).decode('utf-8'))
except Exception as e:
    print(json.dumps({'error': 'could not decode payload: ' + str(e)}))
    sys.exit(1)

if not isinstance(data, dict):
    print(json.dumps({'error': 'payload must be a flat key-value object'}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({'error': 'could not open sheet: ' + str(e)}))
    sys.exit(1)

# Find Settings tab (case-insensitive)
ws = next((w for w in wb.worksheets() if w.title.lower() == 'settings'), None)
if ws is None:
    print(json.dumps({'error': 'Settings tab not found'}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({'error': 'could not read Settings tab: ' + str(e)}))
    sys.exit(1)

# Build row-index map: Name → list of row numbers (1-indexed), to support duplicate keys
row_map = {}
for i, row in enumerate(all_values):
    key = row[0].strip() if row else ''
    if key:
        row_map.setdefault(key, []).append(i + 1)

batch = []
to_append = []

for name, value in data.items():
    existing = row_map.get(name, [])
    if isinstance(value, list):
        # Array: write one row per element; update existing rows, append extras, clear leftovers
        for idx, item in enumerate(value):
            if idx < len(existing):
                r = existing[idx]
                batch.append({'range': f'A{r}:B{r}', 'values': [[name, str(item)]]})
            else:
                to_append.append([name, str(item)])
        # Clear any extra existing rows beyond the new array length
        for idx in range(len(value), len(existing)):
            r = existing[idx]
            batch.append({'range': f'A{r}:B{r}', 'values': [['', '']]})
    else:
        if existing:
            r = existing[0]
            batch.append({'range': f'A{r}:B{r}', 'values': [[name, str(value)]]})
        else:
            to_append.append([name, str(value)])

try:
    if batch:
        ws.batch_update(batch)
    for row in to_append:
        ws.append_row(row, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({'error': 'could not write to Settings tab: ' + str(e)}))
    sys.exit(1)

print(json.dumps({'ok': True, 'updated': len(batch), 'appended': len(to_append)}))
