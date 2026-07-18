# ginitfitboard.py — initialise a Google Spreadsheet for FitBoard
#
# Creates the required "Week" worksheet with the correct column headers
# if it doesn't already exist. Existing tabs are left completely untouched.
#
# Arg: {sheet_id}

import gspread
import sys, os, json

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

sheet_id = sys.argv[1].strip() if len(sys.argv) > 1 else ''
if not sheet_id:
    print(json.dumps({"error": "Sheet ID is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

HEADERS = [
    'Date', 'Done', 'Day', 'Exercise',
    'YT Video Link',
    'Target Sets/Reps',
    'Weight (lbs)', 'Weight (kg)',
    'Set 1', 'Set 2', 'Set 3', 'Set 4',
    'Total Reps', 'Total Volume (lbs)', 'My Notes',
]

existing = {w.title.lower(): w for w in wb.worksheets()}
result   = {}

try:
    if 'week' in existing:
        result['Week'] = 'ok'
    else:
        ws = wb.add_worksheet(title='Week', rows=500, cols=len(HEADERS))
        end_cell = gspread.utils.rowcol_to_a1(1, len(HEADERS))
        ws.update([HEADERS], f'A1:{end_cell}', value_input_option='RAW')
        ws.freeze(rows=1)
        ws.format(f'A1:{end_cell}', {
            'backgroundColor': {'red': 0.027, 'green': 0.404, 'blue': 0.847},  # #0a84ff
            'textFormat': {
                'bold': True,
                'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
            },
            'horizontalAlignment': 'LEFT',
        })
        result['Week'] = 'created'
except Exception as e:
    result['Week'] = f'error: {str(e)}'

all_ok = all(v in ('ok', 'created') for v in result.values())

print(json.dumps({
    "ok":    all_ok,
    "tabs":  result,
    "title": wb.title,
}))
