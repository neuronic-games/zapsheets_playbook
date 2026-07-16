# gread_week.py — fetch Week tab from Google Sheet → stdout as JSON
# Args: {sheet_id}

import gspread
import sys, os, json

cred_file = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(cred_file):
    print(json.dumps({'error': 'credentials.json not found'}))
    sys.exit(1)

try:
    gc = gspread.service_account(filename=cred_file)
except Exception as e:
    print(json.dumps({'error': f'Auth failed: {str(e)}'}))
    sys.exit(1)

sheet_id = sys.argv[1].strip()

try:
    spreadsheet = gc.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({'error': f'Cannot open spreadsheet: {str(e)}'}))
    sys.exit(1)

# Find 'Week' worksheet (case-insensitive)
ws = next((w for w in spreadsheet.worksheets() if w.title.lower() == 'week'), None)
if ws is None:
    available = [w.title for w in spreadsheet.worksheets()]
    print(json.dumps({'error': f'Week worksheet not found. Available: {available}'}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({'error': f'Could not read sheet: {str(e)}'}))
    sys.exit(1)

if not all_values:
    print(json.dumps({'error': 'Sheet is empty'}))
    sys.exit(1)

headers = [h.strip() for h in all_values[0]]
rows = []
for row in all_values[1:]:
    # Skip completely empty rows
    if not any(v.strip() for v in row):
        continue
    # Pad row to header length if needed
    padded = row + [''] * (len(headers) - len(row))
    rows.append(dict(zip(headers, [v.strip() for v in padded])))

print(json.dumps({'ok': True, 'data': rows}))
