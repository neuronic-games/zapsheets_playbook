# ginitsheet.py — initialise a Google Spreadsheet for PitchBoard
#
# Creates required worksheets (Pitches, Games, People, Settings) with the
# correct column headers if they don't already exist.  Existing tabs are
# left completely untouched.  Never deletes any worksheet.
#
# Arg: {sheet_id}
#
# NOTE: the spreadsheet must already be shared with the service-account email
# before calling this script.

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

# ── Tab definitions ────────────────────────────────────────────────────────────
#
# Each entry is a list of rows to write starting at A1.
# For Pitches / Games / People: first row = headers, rest = data.
# For Settings: no header row — just the label/value pairs.

TABS = {
    'Pitches': [
        ['Game', 'Publisher', 'Contact', 'Date', 'Event', 'Status', 'Notes'],
    ],
    'Games': [
        ['Name', 'Tagline', 'Status',
         'Date Started', 'Date Signed', 'Date Published',
         'Designer1', 'Designer2', 'Designer3', 'Designer4',
         'Description', 'Rules', 'Play', 'Print', 'Sellsheet', 'BGG', 'Video'],
    ],
    'People': [
        ['Name', 'Email', 'Company', 'Role', 'Notes'],
    ],
    'Settings': [
        ['My Name',     ''],
        ['My Email',    ''],
        ['My Phone',    ''],
        ['PublishedOn', ''],
        ['Version',     ''],
    ],
}

results = {}

# Build map of currently existing worksheets (by title)
existing = {w.title: w for w in wb.worksheets()}

# ── Create missing required worksheets only ────────────────────────────────────
# If a tab already exists we leave it completely untouched — never clear or
# overwrite it.  We never delete any worksheet so that game-specific tabs
# (e.g. [Monopoly]) created by gcreategametab.py are preserved.
for tab_name, rows in TABS.items():
    try:
        if tab_name in existing:
            # Tab exists — data is intact, nothing to do.
            results[tab_name] = 'ok'
        else:
            num_cols = max(len(r) for r in rows) if rows else 2
            ws = wb.add_worksheet(title=tab_name, rows=200, cols=num_cols)
            if rows:
                end_cell = gspread.utils.rowcol_to_a1(len(rows), max(len(r) for r in rows))
                ws.update(rows, f'A1:{end_cell}', value_input_option='RAW')
            results[tab_name] = 'created'
    except Exception as e:
        results[tab_name] = f'error: {str(e)}'

tab_results = {k: v for k, v in results.items()}
all_ok = all(v in ('ok', 'created') for v in tab_results.values())

print(json.dumps({
    "ok":    all_ok,
    "tabs":  tab_results,
    "title": wb.title,
}))
