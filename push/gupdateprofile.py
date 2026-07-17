# gupdateprofile.py — update Name/Email/Phone in the settings sheet
#
# Settings sheet structure:
#   Row 1 (headers): "My Name" | <person_name>      ← name is the column header
#   Row N:            "My Email" | <email_value>
#   Row N:            "My Phone" | <phone_value>
#
# Arg: {sheet_id}|{base64_encoded_json}
# JSON: { "name": "...", "email": "...", "phone": "..." }

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
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1] if len(sys.argv) > 1 else ''
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

new_name  = data.get('name',  '').strip()
new_email = data.get('email', '').strip()
new_phone = data.get('phone', '').strip()

if not new_name:
    print(json.dumps({"error": "Name is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws = wb.worksheets()
ws = next((w for w in all_ws if w.title.lower() == 'settings'), None)
if ws is None:
    print(json.dumps({"error": "Settings worksheet not found"}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read settings sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Settings sheet is empty"}))
    sys.exit(1)

updates = []

# Row 1 col 2 = the person's name (column header)
# Update the header cell (row 1, col 2) with the new name
updates.append({
    'range':  gspread.utils.rowcol_to_a1(1, 2),
    'values': [[safe_str(new_name)]]
})

# Find rows for My Email and My Phone (col 1 label, col 2 value)
for i, row in enumerate(all_values[1:], start=2):
    label = row[0].strip() if row else ''
    if label == 'My Email':
        updates.append({'range': gspread.utils.rowcol_to_a1(i, 2), 'values': [[safe_str(new_email)]]})
    elif label == 'My Phone':
        updates.append({'range': gspread.utils.rowcol_to_a1(i, 2), 'values': [[safe_str(new_phone)]]})

try:
    ws.batch_update(updates, value_input_option='USER_ENTERED')
    print(json.dumps({"ok": True, "updated": len(updates)}))
except Exception as e:
    print(json.dumps({"error": f"Could not update settings: {str(e)}"}))
    sys.exit(1)
