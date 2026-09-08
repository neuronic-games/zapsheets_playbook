# gresizerow.py — set a row's pixel height in a Google Sheet
# Arg: {sheet_id}|{tab_name}|{row_number_1indexed}|{pixel_height}

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
    print(json.dumps({"error": f"Auth failed: {str(e)}"}))
    sys.exit(1)

arg   = sys.argv[1] if len(sys.argv) > 1 else ''
parts = arg.split('|', 3)
if len(parts) < 4:
    print(json.dumps({"error": "Expected: sheet_id|tab_name|row_number|pixel_height"}))
    sys.exit(1)

sheet_id   = parts[0].strip()
tab_name   = parts[1].strip()
row_1idx   = int(parts[2].strip())   # 1-indexed sheet row
pixel_h    = int(parts[3].strip())

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

ws = next((w for w in wb.worksheets() if w.title == tab_name), None)
if not ws:
    print(json.dumps({"error": f"Tab '{tab_name}' not found"}))
    sys.exit(1)

row_0idx = row_1idx - 1   # 0-indexed for the API
try:
    wb.batch_update({'requests': [{
        'updateDimensionProperties': {
            'range': {
                'sheetId':    ws.id,
                'dimension':  'ROWS',
                'startIndex': row_0idx,
                'endIndex':   row_0idx + 1,
            },
            'properties': {'pixelSize': pixel_h},
            'fields':     'pixelSize',
        }
    }]})
    print(json.dumps({"ok": True, "row": row_1idx, "pixelSize": pixel_h}))
except Exception as e:
    print(json.dumps({"error": f"Resize failed: {str(e)}"}))
    sys.exit(1)
