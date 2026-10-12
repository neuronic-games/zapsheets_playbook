# gtmStaffUpsert.py — set a person's Name on the T&MBoard Staff tab, keyed by Email
# Arg: {sheet_id}|{base64_json}   JSON: { "name": "...", "email": "..." }
# Updates the Name on the row whose Email matches (case-insensitive);
# appends a new Name/Email row if the email isn't on the tab yet.

import gspread
import sys, os, json, base64, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    s = (str(v) if v is not None else '').strip()
    return ("'" + s) if s else ''

def clean(v):
    return (v or '').strip().lstrip("'").strip()

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"})); sys.exit(1)

try:
    sheet_id, json_b64 = sys.argv[1].split('|', 1)
    data  = json.loads(base64.b64decode(json_b64).decode('utf-8'))
    name  = clean(data.get('name'))
    email = clean(data.get('email')).lower()
except Exception as e:
    print(json.dumps({"error": f"Bad argument: {e}"})); sys.exit(1)

if not name or not email:
    print(json.dumps({"error": "name and email are required"})); sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
    ss = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {e}"})); sys.exit(1)

ws = next((w for w in ss.worksheets() if w.title.lower() == 'staff'), None)
if ws is None:
    print(json.dumps({"error": "Worksheet 'Staff' not found"})); sys.exit(1)

try:
    values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read Staff tab: {e}"})); sys.exit(1)

if not values or not any(c.strip() for c in values[0]):
    print(json.dumps({"error": "Staff tab has no header row"})); sys.exit(1)

headers = values[0]
col = {h.strip().lower(): i for i, h in enumerate(headers)}
name_i, email_i = col.get('name', -1), col.get('email', -1)
if name_i < 0 or email_i < 0:
    print(json.dumps({"error": "Staff tab needs 'Name' and 'Email' columns"})); sys.exit(1)

try:
    for r, row in enumerate(values[1:], start=2):
        cell = clean(row[email_i]) if email_i < len(row) else ''
        if cell.lower() == email:
            ws.update_acell(gspread.utils.rowcol_to_a1(r, name_i + 1), safe_str(name))
            print(json.dumps({"ok": True, "action": "updated", "row": r})); sys.exit(0)
    new_row = [''] * len(headers)
    new_row[name_i], new_row[email_i] = safe_str(name), safe_str(email)
    ws.append_rows([new_row], value_input_option='USER_ENTERED', insert_data_option='OVERWRITE')
    print(json.dumps({"ok": True, "action": "added"}))
except SystemExit:
    raise
except Exception as e:
    print(json.dumps({"error": f"Could not write Staff tab: {e}"})); sys.exit(1)
