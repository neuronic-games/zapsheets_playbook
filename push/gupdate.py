# gupdate.py — update Date, Event, Status, Contact, Notes fields of a matching pitches row
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: game, publisher, orig_contact, orig_date, orig_event,
#            contact, date, event, status, notes

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    mServiceAccount = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

# Parse arg: {sheet_id}|{base64_json}
arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

game         = data.get('game',         '').strip()
publisher    = data.get('publisher',    '').strip()
orig_contact = data.get('orig_contact', '').strip()
orig_date    = data.get('orig_date',    '').strip()
orig_event   = data.get('orig_event',  '').strip()
new_contact  = data.get('contact',     '').strip()
new_date     = data.get('date',        '').strip()
new_event    = data.get('event',       '').strip()
new_status   = data.get('status',      '').strip()
new_notes    = data.get('notes',       '')   # preserve internal whitespace

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find pitches worksheet (case-insensitive)
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == 'pitches'), None)
if ws is None:
    print(json.dumps({"error": "Pitches worksheet not found"}))
    sys.exit(1)

# Read all values
try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Sheet is empty"}))
    sys.exit(1)

# Build column-name → index map from header row
headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def cell(row, field):
    idx = col.get(field, -1)
    return row[idx].strip() if 0 <= idx < len(row) else ''

# Use original values as lookup key (supports editing those fields)
lookup_contact = orig_contact or new_contact
lookup_date    = orig_date    or new_date
lookup_event   = orig_event   or new_event

# Search rows (header is row 0; sheet rows are 1-indexed → data row i+1 = sheet row i+2)
target_sheet_row = None
for i, row in enumerate(all_values[1:], start=2):
    if (cell(row, 'Game')      == game           and
        cell(row, 'Publisher') == publisher       and
        cell(row, 'Contact')   == lookup_contact  and
        cell(row, 'Date')      == lookup_date     and
        cell(row, 'Event')     == lookup_event):
        target_sheet_row = i
        break

if target_sheet_row is None:
    print(json.dumps({"error": "Matching row not found in sheet"}))
    sys.exit(1)

# Build batch update: only update columns that exist in the sheet
field_map = [
    ('Date',    new_date),
    ('Contact', new_contact),
    ('Event',   new_event),
    ('Status',  new_status),
    ('Notes',   new_notes),
]
updates = []
for field, value in field_map:
    idx = col.get(field, -1)
    if idx >= 0:
        updates.append({
            'range':  gspread.utils.rowcol_to_a1(target_sheet_row, idx + 1),
            'values': [[value]]
        })

if not updates:
    print(json.dumps({"error": "No matching columns found in sheet headers"}))
    sys.exit(1)

try:
    ws.batch_update(updates, value_input_option='USER_ENTERED')
    print(json.dumps({"ok": True, "row": target_sheet_row}))
except Exception as e:
    print(json.dumps({"error": f"Could not update cells: {str(e)}"}))
    sys.exit(1)
