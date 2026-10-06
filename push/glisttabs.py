# glisttabs.py — list worksheet titles that start with a given prefix
# Arg: {sheet_id}|{prefix}
# Returns: {"ok": true, "tabs": ["[Game1]", "[Game1] dev", ...]}

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

arg      = sys.argv[1]
pipe_idx = arg.index('|') if '|' in arg else len(arg)
sheet_id = arg[:pipe_idx]
prefix   = arg[pipe_idx + 1:] if pipe_idx < len(arg) else ''

try:
    wb     = sa.open_by_key(sheet_id)
    titles = [ws.title for ws in wb.worksheets()
              if not prefix or ws.title.startswith(prefix)]
    print(json.dumps({"ok": True, "tabs": titles}))
except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit(1)
