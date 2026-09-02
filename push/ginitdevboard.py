# ginitdevboard.py — initialise DevBoard for a Google Spreadsheet.
#
# Creates Games + Settings tabs if they don't already exist.
# If the sheet is already a PitchBoard sheet, those tabs exist and are left
# completely untouched.  Per-game "{GameName} dev" tabs are NOT created here —
# they are created manually in Google Sheets as data is added.
#
# Arg:     {sheet_id}
# Returns: {"ok": true, "title": "...", "tabs_created": [...]}
#       or {"error": "..."}

import gspread
import sys, os, json, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

sheet_id = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
if not sheet_id:
    print(json.dumps({"error": "sheet_id argument required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

TABS = {
    'Games': {
        'rows': [['Name', 'Tagline', 'Status',
                  'Date Started', 'Date Signed', 'Date Published',
                  'Designer1', 'Designer2', 'Designer3', 'Designer4',
                  'Description', 'Rules', 'Play', 'Print', 'Sellsheet', 'BGG', 'Video']],
        'freeze': True,
    },
    'Settings': {
        'rows': [
            ['My Name',     ''],
            ['My Email',    ''],
            ['My Phone',    ''],
            ['PublishedOn', ''],
            ['Version',     ''],
        ],
        'freeze': False,
    },
}

existing   = {w.title: w for w in wb.worksheets()}
tabs_created = []

for tab_name, cfg in TABS.items():
    if tab_name in existing:
        continue
    try:
        rows = cfg['rows']
        cols = max(len(r) for r in rows)
        ws   = wb.add_worksheet(title=tab_name, rows=200, cols=cols)
        ws.update(rows, 'A1', value_input_option='USER_ENTERED')
        if cfg['freeze']:
            ws.freeze(rows=1)
            last_col = chr(ord('A') + cols - 1)
            ws.format(f'A1:{last_col}1', {
                'backgroundColor': {'red': 0.102, 'green': 0.102, 'blue': 0.180},
                'textFormat': {
                    'bold': True,
                    'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
                },
            })
        tabs_created.append(tab_name)
    except Exception as e:
        print(json.dumps({"error": f"Failed to create '{tab_name}' tab: {str(e)}"}))
        sys.exit(1)

print(json.dumps({"ok": True, "title": wb.title, "tabs_created": tabs_created}))
