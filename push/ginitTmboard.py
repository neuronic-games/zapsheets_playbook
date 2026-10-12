# ginitTmboard.py — initialise a Google Sheet for T&MBoard
# Arg: {sheet_id}
# Creates tabs: Clients, Projects, Time, Hardware, Staff
# Returns JSON: { ok, title, tabs_created }

import gspread
from gspread.exceptions import APIError
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

sheet_id = sys.argv[1].strip()

try:
    ss = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

existing = {ws.title for ws in ss.worksheets()}

TABS = {
    'Clients':  ['Ref Number', 'Client', 'Contact Email', 'Phone', 'Notes'],
    'Projects': ['Ref Number', 'Client', 'Project', 'Status', 'Estimate Hours', 'Start Date', 'End Date', 'Notes'],
    'Time':     ['Ref Number', 'Date', 'Project', 'Client', 'Staff Name', 'Hours', 'Description'],
    'Hardware': ['Ref Number', 'Date', 'Project', 'Client', 'Description', 'Amount', 'Status', 'Buy URL', 'Notes', 'Added By'],
    'Staff':    ['Name', 'Email'],
}

PROJECT_STATUSES = ['Active', 'On Hold', 'Completed', 'Cancelled']

tabs_created = []
for tab_name, headers in TABS.items():
    if tab_name not in existing:
        ws = ss.add_worksheet(title=tab_name, rows=1000, cols=len(headers))
        ws.append_row(headers, value_input_option='RAW')

        # Apply header formatting
        ws.format('1:1', {
            'textFormat': {'bold': True},
            'backgroundColor': {'red': 0.23, 'green': 0.23, 'blue': 0.67}  # #3b3baa
        })
        ws.format('1:1', {'textFormat': {'bold': True, 'foregroundColor': {'red': 1, 'green': 1, 'blue': 1}}})

        # Freeze header row
        ss.batch_update({'requests': [{
            'updateSheetProperties': {
                'properties': {'sheetId': ws.id, 'gridProperties': {'frozenRowCount': 1}},
                'fields': 'gridProperties.frozenRowCount'
            }
        }]})

        # Add validation for Projects.Status
        if tab_name == 'Projects':
            status_col = headers.index('Status') if 'Status' in headers else -1
            if status_col >= 0:
                ss.batch_update({'requests': [{
                    'setDataValidation': {
                        'range': {
                            'sheetId': ws.id,
                            'startRowIndex': 1, 'endRowIndex': 10000,
                            'startColumnIndex': status_col, 'endColumnIndex': status_col + 1,
                        },
                        'rule': {
                            'condition': {
                                'type': 'ONE_OF_LIST',
                                'values': [{'userEnteredValue': v} for v in PROJECT_STATUSES],
                            },
                            'showCustomUi': True,
                            'strict': False,
                        }
                    }
                }]})

        tabs_created.append(tab_name)

print(json.dumps({
    'ok': True,
    'title': ss.title,
    'tabs_created': tabs_created,
}))
