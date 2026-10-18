# gfixgamesheader.py — restore cell A1 of the Games tab to the string "Name".
#
# Arg:  "{sheet_id}"
# Returns: {"ok": true} or {"error": "..."}

import gspread
import sys, os, json

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": "Could not authenticate: " + str(e)}))
    sys.exit(1)

sheet_id = sys.argv[1].strip() if len(sys.argv) > 1 else ''
if not sheet_id:
    print(json.dumps({"error": "sheet_id is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "Could not open spreadsheet: " + str(e)}))
    sys.exit(1)

ws = None
for name in ['Games', 'games', 'GAMES']:
    try:
        ws = wb.worksheet(name)
        break
    except gspread.exceptions.WorksheetNotFound:
        pass
if ws is None:
    ws = wb.get_worksheet(0)
if ws is None:
    print(json.dumps({"error": "Could not find Games worksheet"}))
    sys.exit(1)

try:
    # Read A1 first so we can report what it currently contains
    current = ws.acell('A1').value
    # Write "Name" to A1 as a plain string (RAW so it is never interpreted as a formula)
    ws.update('A1', [['Name']], value_input_option='RAW')
    print(json.dumps({"ok": True, "was": current, "now": "Name"}))
except Exception as e:
    print(json.dumps({"error": "Could not update A1: " + str(e)}))
    sys.exit(1)
