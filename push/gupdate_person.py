# gupdate_person.py — update a person row in the People sheet
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: orig_name, name, email, company, role, notes

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets formula interpretation."""
    s = (str(v) if v is not None else '').strip()
    return ("'" + s) if s else ''

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    mServiceAccount = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

orig_name = data.get('orig_name', '').strip()
new_name  = data.get('name',      '').strip()
email     = data.get('email',     '').strip()
company   = data.get('company',   '').strip()
role      = data.get('role',      '').strip()
notes     = data.get('notes',     '')

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find People worksheet (case-insensitive)
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == 'people'), None)
if ws is None:
    print(json.dumps({"error": "People worksheet not found"}))
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

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def cell(row, field):
    idx = col.get(field, -1)
    return row[idx].strip() if 0 <= idx < len(row) else ''

# Find row by original name
target_sheet_row = None
for i, row in enumerate(all_values[1:], start=2):
    if cell(row, 'Name') == orig_name:
        target_sheet_row = i
        break

field_map = [
    ('Name',    new_name or orig_name),
    ('Email',   email),
    ('Company', company),
    ('Role',    role),
    ('Notes',   notes),
]

if target_sheet_row is None:
    # Person not found — INSERT a new row instead of failing (upsert behaviour).
    # Build a row vector aligned to the existing header order.
    new_row = [''] * len(headers)
    for field, value in field_map:
        idx = col.get(field, -1)
        if 0 <= idx < len(new_row):
            new_row[idx] = safe_str(value) if value else ''
    try:
        ws.append_row(new_row, value_input_option='USER_ENTERED')
        print(json.dumps({"ok": True, "created": True}))
    except Exception as e:
        print(json.dumps({"error": f"Could not add person: {str(e)}"}))
        sys.exit(1)
    sys.exit(0)

# Person found — UPDATE the existing row.
updates = []
for field, value in field_map:
    idx = col.get(field, -1)
    if idx >= 0:
        updates.append({
            'range':  gspread.utils.rowcol_to_a1(target_sheet_row, idx + 1),
            'values': [[safe_str(value)]]
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
