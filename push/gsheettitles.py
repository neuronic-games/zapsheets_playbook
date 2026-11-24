# gsheettitles.py — the Google Sheet name (file title) of one or more spreadsheets
# Args:    {sheet_id} [{sheet_id} …]
# Returns: {"ok": true, "titles": {"<id>": "<name>", …}}   (a sheet that can't be opened is left out)

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, socket

socket.setdefaulttimeout(15)
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

titles = {}
for sheet_id in sys.argv[1:]:
    try:
        titles[sheet_id] = sa.open_by_key(sheet_id).title
    except Exception:
        pass
print(json.dumps({"ok": True, "titles": titles}))
