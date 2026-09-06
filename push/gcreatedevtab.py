# gcreatedevtab.py — create a [GameName] dev tab for DevBoard.
#
# Creates the worksheet "[{game_name}] dev" with column headers:
#   Date | Event | Observation | Solution
#
# If the tab already exists, does nothing and returns ok.
#
# Arg:     "{sheet_id}|{game_name}"
# Returns: {"ok": true, "tab": "[GameName] dev", "created": true/false}
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

if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game_name are required"}))
    sys.exit(1)

tab_name = f'[{game_name}] dev'

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

existing = {w.title: w for w in wb.worksheets()}

if tab_name in existing:
    print(json.dumps({"ok": True, "tab": tab_name, "created": False}))
    sys.exit(0)

HEADERS = [['Date', 'Event', 'People', 'Observation', 'Solution']]

try:
    ws = wb.add_worksheet(title=tab_name, rows=500, cols=5)
    ws.update(HEADERS, 'A1', value_input_option='USER_ENTERED')
    ws.freeze(rows=1)
    # Style header row
    ws.format('A1:E1', {
        'backgroundColor': {'red': 0.102, 'green': 0.373, 'blue': 0.478},
        'textFormat': {
            'bold': True,
            'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
        },
    })
    # Column widths: Date, Event, People, Observation, Solution
    wb.batch_update({'requests': [
        {'updateDimensionProperties': {'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': 0, 'endIndex': 1}, 'properties': {'pixelSize': 130}, 'fields': 'pixelSize'}},
        {'updateDimensionProperties': {'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': 1, 'endIndex': 2}, 'properties': {'pixelSize': 160}, 'fields': 'pixelSize'}},
        {'updateDimensionProperties': {'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': 2, 'endIndex': 3}, 'properties': {'pixelSize': 260}, 'fields': 'pixelSize'}},
        {'updateDimensionProperties': {'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': 3, 'endIndex': 4}, 'properties': {'pixelSize': 300}, 'fields': 'pixelSize'}},
        {'updateDimensionProperties': {'range': {'sheetId': ws.id, 'dimension': 'COLUMNS', 'startIndex': 4, 'endIndex': 5}, 'properties': {'pixelSize': 300}, 'fields': 'pixelSize'}},
    ]})
    # Wrap People, Observation, Solution columns
    ws.format('C2:E500', {'wrapStrategy': 'WRAP'})
    # Conditional formats: color session header rows by type (keyed on Event column B)
    _range = {'sheetId': ws.id, 'startRowIndex': 1, 'endRowIndex': 500,
               'startColumnIndex': 0, 'endColumnIndex': 5}
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
    print(json.dumps({"error": f"Could not create tab '{tab_name}': {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": tab_name, "created": True}))
