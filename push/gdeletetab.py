# gdeletetab.py — delete a worksheet tab from a Google Spreadsheet.
#
# Arg:     "{sheet_id}|{tab_name}"
# Returns: {"ok": true, "tab": "..."}
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

raw = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
if '|' not in raw:
    print(json.dumps({"error": "Argument must be '{sheet_id}|{tab_name}'"}))
    sys.exit(1)

sheet_id, tab_name = raw.split('|', 1)
sheet_id = sheet_id.strip()
tab_name = tab_name.strip()

if not sheet_id or not tab_name:
    print(json.dumps({"error": "sheet_id and tab_name are required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

existing = {w.title: w for w in wb.worksheets()}
if tab_name not in existing:
    # Already gone — treat as success
    print(json.dumps({"ok": True, "tab": tab_name, "already_absent": True}))
    sys.exit(0)

try:
    wb.del_worksheet(existing[tab_name])
except Exception as e:
    print(json.dumps({"error": f"Could not delete tab: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": tab_name}))
