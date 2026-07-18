# gread_week.py — fetch Week tab from Google Sheet → stdout as JSON
# Args: {sheet_id}
#
# The existing sheet uses column names that pre-date the FitBoard JS field names.
# COLUMN_REMAP translates actual sheet headers → the names the JS code expects.
# Sheets created by the new ginitfitboard.py already use the correct names,
# so any key not present in the map is passed through unchanged.

import gspread
import sys, os, json

# Map: actual sheet column header → JS-expected field name
COLUMN_REMAP = {
    'Week':               'Date',
    'Day':                'Done',
    'Exercise':           'Day',
    'Date':               'Exercise',
    'Done':               'YT Video Link',
    'Weight (lbs)':       'Target Sets/Reps',
    'Weight (kg)':        'Weight (lbs)',
    'Set 1':              'Weight (kg)',
    'Set 2':              'Set 1',
    'Set 3':              'Set 2',
    'Set 4':              'Set 3',
    'Total Reps':         'Set 4',
    'Total Volume (lbs)': 'Total Reps',
    'My Notes':           'Total Volume (lbs)',
    '':                   'My Notes',
}

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

raw_headers = [h.strip() for h in all_values[0]]

# Detect whether this sheet uses the legacy column names.
# A sheet created by the new ginitfitboard.py will already have 'Day' containing
# the group label; the legacy sheet has 'Exercise' containing the group label.
# We apply COLUMN_REMAP only when the legacy layout is detected.
needs_remap = (
    'Exercise' in raw_headers and 'YT Video Link' not in raw_headers
)

if needs_remap:
    headers = [COLUMN_REMAP.get(h, h) for h in raw_headers]
else:
    headers = raw_headers

rows = []
for row in all_values[1:]:
    # Skip completely empty rows
    if not any(v.strip() for v in row):
        continue
    # Pad row to header length if needed
    padded = row + [''] * (len(headers) - len(row))
    rows.append(dict(zip(headers, [v.strip() for v in padded])))

print(json.dumps({'ok': True, 'data': rows}))
