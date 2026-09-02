# gapplydevformat.py — apply conditional formatting to an existing [GameName] dev tab.
#
# Adds three conditional format rules (Playtest / Meeting / Idea) based on column B (Event).
# Safe to call on a tab that already has rules — adds at index 0 each time, pushing
# any old rules down (they become inactive as higher-priority rules match first).
#
# Args:    "{sheet_id}|{game_name}"
# Returns: {"ok": true, "tab": "[GameName] dev"}
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
    print(json.dumps({"error": "Argument must be '{sheet_id}|{game_name}'"}))
    sys.exit(1)

sheet_id, game_name = raw.split('|', 1)
sheet_id  = sheet_id.strip()
game_name = game_name.strip()
tab_name  = f'[{game_name}] dev'

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

existing = {w.title: w for w in wb.worksheets()}
if tab_name not in existing:
    print(json.dumps({"error": f"Tab '{tab_name}' not found"}))
    sys.exit(1)

ws = existing[tab_name]
_range = {
    'sheetId': ws.id,
    'startRowIndex': 1, 'endRowIndex': 500,
    'startColumnIndex': 0, 'endColumnIndex': 4,
}

try:
    wb.batch_update({'requests': [
        # Playtest → light teal
        {'addConditionalFormatRule': {'index': 0, 'rule': {
            'ranges': [_range],
            'booleanRule': {
                'condition': {'type': 'CUSTOM_FORMULA',
                              'values': [{'userEnteredValue': '=REGEXMATCH($B2,"(?i)^Playtest")'}]},
                'format': {'backgroundColor': {'red': 0.80, 'green': 0.91, 'blue': 0.94}},
            },
        }}},
        # Meeting → light purple
        {'addConditionalFormatRule': {'index': 1, 'rule': {
            'ranges': [_range],
            'booleanRule': {
                'condition': {'type': 'CUSTOM_FORMULA',
                              'values': [{'userEnteredValue': '=REGEXMATCH($B2,"(?i)^Meeting")'}]},
                'format': {'backgroundColor': {'red': 0.882, 'green': 0.835, 'blue': 0.957}},
            },
        }}},
        # Idea → light green
        {'addConditionalFormatRule': {'index': 2, 'rule': {
            'ranges': [_range],
            'booleanRule': {
                'condition': {'type': 'CUSTOM_FORMULA',
                              'values': [{'userEnteredValue': '=REGEXMATCH($B2,"(?i)^Idea")'}]},
                'format': {'backgroundColor': {'red': 0.80, 'green': 0.929, 'blue': 0.855}},
            },
        }}},
    ]})
except Exception as e:
    print(json.dumps({"error": f"Could not apply formatting: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": tab_name}))
