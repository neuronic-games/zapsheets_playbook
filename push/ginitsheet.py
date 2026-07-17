# ginitsheet.py — initialise a brand-new Google Spreadsheet for PitchBoard
#
# Creates four worksheets (Pitches, Games, People, Settings) with the correct
# column headers and default Settings rows.  Any worksheet not in the required
# set (e.g. the default "Sheet1") is deleted after the required tabs exist.
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

# ── Rename spreadsheet to [Title] if not already bracketed ────────────────────
# Brackets make PitchBoard sheets visually distinct from other spreadsheets
# in Google Drive (e.g. "My Game" → "[My Game]").  Idempotent.
try:
    current_title = wb.title
    if not (current_title.startswith('[') and current_title.endswith(']')):
        wb.update_title('[' + current_title + ']')
except Exception:
    pass   # non-fatal — sheet still initialises correctly without a rename

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
        ['My Name',  ''],
        ['My Email', ''],
        ['My Phone', ''],
    ],
}

results   = {}
tab_order = list(TABS.keys())   # preserve insertion order

# Build map of currently existing worksheets
existing = {w.title: w for w in wb.worksheets()}

# ── Create / update required worksheets ───────────────────────────────────────
for tab_name, rows in TABS.items():
    try:
        num_cols = max(len(r) for r in rows) if rows else 2
        if tab_name in existing:
            ws = existing[tab_name]
        else:
            ws = wb.add_worksheet(title=tab_name, rows=200, cols=num_cols)

        ws.clear()

        if rows:
            end_cell  = gspread.utils.rowcol_to_a1(len(rows), max(len(r) for r in rows))
            ws.update(range_name=f'A1:{end_cell}', values=rows, value_input_option='RAW')

        results[tab_name] = 'ok'
    except Exception as e:
        results[tab_name] = f'error: {str(e)}'

# ── Delete any worksheet not in the required set ───────────────────────────────
# Re-fetch the worksheet list so we see any tabs added above.
try:
    current_ws = wb.worksheets()
    required   = set(TABS.keys())
    for ws in current_ws:
        if ws.title not in required:
            try:
                wb.del_worksheet(ws)
                results[f'_deleted_{ws.title}'] = True
            except Exception as e:
                results[f'_delete_error_{ws.title}'] = str(e)
except Exception as e:
    results['_cleanup_error'] = str(e)

tab_results = {k: v for k, v in results.items() if not k.startswith('_')}
all_ok = all(v == 'ok' for v in tab_results.values())

print(json.dumps({
    "ok":    all_ok,
    "tabs":  tab_results,
    "title": wb.title,
}))
