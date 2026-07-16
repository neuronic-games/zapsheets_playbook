# gupdate_fitboard.py — batch-update exercise rows in the Week sheet
# Arg: {sheet_id}|{base64_encoded_json_array_of_exercise_rows}
#
# Each item in the array must have: Day, Exercise, and any editable fields.
# Matches rows in the sheet by Day + Exercise columns, then batch-updates
# Date, Done, Weight (lbs), Weight (kg), Set 1–4, Total Reps,
# Total Volume (lbs), My Notes.

import gspread
import gspread.utils
import sys, os, json, base64

EDITABLE = [
    'Date', 'Done', 'Weight (lbs)', 'Weight (kg)',
    'Set 1', 'Set 2', 'Set 3', 'Set 4',
    'Total Reps', 'Total Volume (lbs)', 'My Notes'
]

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({'error': 'credentials.json not found'}))
    sys.exit(1)

try:
    mServiceAccount = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({'error': f'Could not authenticate: {str(e)}'}))
    sys.exit(1)

arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
exercises = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({'error': f'Could not open spreadsheet: {str(e)}'}))
    sys.exit(1)

# Find the Week worksheet (case-insensitive)
all_ws = mGoogleSheet.worksheets()
ws = next((w for w in all_ws if w.title.lower() == 'week'), None)
if ws is None:
    available = [w.title for w in all_ws]
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

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def cell_val(row, field):
    idx = col.get(field, -1)
    return row[idx].strip() if 0 <= idx < len(row) else ''

# Build lookup: (Day, Exercise) → sheet row number (1-indexed)
row_map = {}
for i, row in enumerate(all_values[1:], start=2):
    day_val = cell_val(row, 'Day')
    ex_val  = cell_val(row, 'Exercise')
    if day_val and ex_val:
        row_map[(day_val, ex_val)] = i

# Build batch updates
all_updates = []
updated = 0
not_found = []

for ex in exercises:
    day = ex.get('Day', '').strip()
    exercise = ex.get('Exercise', '').strip()
    sheet_row = row_map.get((day, exercise))
    if sheet_row is None:
        not_found.append(f'{day} / {exercise}')
        continue
    for field in EDITABLE:
        if field not in col:
            continue
        if field not in ex:
            continue
        cell_ref = gspread.utils.rowcol_to_a1(sheet_row, col[field] + 1)
        all_updates.append({
            'range': cell_ref,
            'values': [[ex[field]]]
        })
    updated += 1

if not all_updates:
    msg = f'No matching rows found in sheet.'
    if not_found:
        msg += f' Not found: {not_found[:3]}'
    print(json.dumps({'error': msg}))
    sys.exit(1)

try:
    ws.batch_update(all_updates, value_input_option='USER_ENTERED')
    result = {'ok': True, 'updated': updated}
    if not_found:
        result['not_found'] = not_found
    print(json.dumps(result))
except Exception as e:
    print(json.dumps({'error': f'Could not update cells: {str(e)}'}))
    sys.exit(1)
