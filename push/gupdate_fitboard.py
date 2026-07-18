# gupdate_fitboard.py — batch-update exercise rows in the Week sheet
# Arg: {sheet_id}|{base64_encoded_json_array_of_exercise_rows}
#
# Each item in the array must have: Day, Exercise, and any editable fields
# (using the JS / gread_week.py field names).
# Matches rows in the sheet by Day + Exercise values, then batch-updates
# the editable fields, reverse-remapping field names back to actual sheet
# column names for legacy sheets.

import gspread
import gspread.utils
import sys, os, json, base64

# JS field names used by the app / gread_week.py output
EDITABLE = [
    'Date', 'Done', 'Weight (lbs)', 'Weight (kg)',
    'Set 1', 'Set 2', 'Set 3', 'Set 4',
    'Total Reps', 'Total Volume (lbs)', 'My Notes',
]

# Reverse of gread_week.py's COLUMN_REMAP:
# JS field name → actual legacy sheet column name
JS_TO_SHEET = {
    'Date':                'Week',
    'Done':                'Day',
    'Day':                 'Exercise',
    'Exercise':            'Date',
    'YT Video Link':       'Done',
    'Target Sets/Reps':    'Weight (lbs)',
    'Weight (lbs)':        'Weight (kg)',
    'Weight (kg)':         'Set 1',
    'Set 1':               'Set 2',
    'Set 2':               'Set 3',
    'Set 3':               'Set 4',
    'Set 4':               'Total Reps',
    'Total Reps':          'Total Volume (lbs)',
    'Total Volume (lbs)':  'My Notes',
    'My Notes':            '',
}

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

raw_headers = all_values[0]
sheet_col = {h.strip(): i for i, h in enumerate(raw_headers)}

# Detect legacy layout (same heuristic as gread_week.py):
# legacy sheets have 'Exercise' containing the group label, not 'YT Video Link'.
is_legacy = (
    'Exercise' in sheet_col and 'YT Video Link' not in sheet_col
)

def sheet_col_name(js_name):
    """Return the actual sheet column name for a JS field name."""
    if is_legacy:
        return JS_TO_SHEET.get(js_name, js_name)
    return js_name

def cell_val(row, sheet_header):
    idx = sheet_col.get(sheet_header, -1)
    return row[idx].strip() if 0 <= idx < len(row) else ''

# Build lookup: (day_label, exercise_name) → sheet row number (1-indexed)
# Key columns differ by layout:
#   legacy: group label in 'Exercise', exercise name in 'Date'
#   new:    group label in 'Day',      exercise name in 'Exercise'
day_col  = sheet_col_name('Day')       # actual sheet col for the group label
ex_col   = sheet_col_name('Exercise')  # actual sheet col for the exercise name

row_map = {}
for i, row in enumerate(all_values[1:], start=2):
    day_val = cell_val(row, day_col)
    ex_val  = cell_val(row, ex_col)
    if day_val and ex_val:
        row_map[(day_val, ex_val)] = i

# Build batch updates
all_updates = []
updated = 0
not_found = []

for ex in exercises:
    day      = ex.get('Day', '').strip()
    exercise = ex.get('Exercise', '').strip()
    sheet_row = row_map.get((day, exercise))
    if sheet_row is None:
        not_found.append(f'{day} / {exercise}')
        continue
    for js_field in EDITABLE:
        actual_col = sheet_col_name(js_field)
        if actual_col not in sheet_col:
            continue
        if js_field not in ex:
            continue
        cell_ref = gspread.utils.rowcol_to_a1(sheet_row, sheet_col[actual_col] + 1)
        all_updates.append({
            'range': cell_ref,
            'values': [[ex[js_field]]]
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
