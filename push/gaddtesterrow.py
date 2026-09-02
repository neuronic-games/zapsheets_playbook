# gaddtesterrow.py — append a right-aligned tester row to a [GameName] dev tab.
#
# Appends: Date="", Event="", Observation=name (right-aligned), Solution=email
# Then formats column C of that row as RIGHT-aligned.
#
# Args:    "{sheet_id}|{tab_name}|{name}|{email}"
# Returns: {"ok": true, "row_num": N, "row": {...}}
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
parts = raw.split('|', 3)
if len(parts) < 3:
    print(json.dumps({"error": "Argument must be '{sheet_id}|{tab_name}|{name}|{email}'"}))
    sys.exit(1)

sheet_id = parts[0].strip()
tab_name = parts[1].strip()
name     = parts[2].strip()
email    = parts[3].strip() if len(parts) > 3 else ''

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

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

# Build the row: blank Date/Event, name in Observation, email in Solution
# Prefix with ' to prevent formula interpretation (same as gadd.py)
def safe(v):
    s = (str(v) if v else '').strip()
    return ("'" + s) if s else ''

new_row  = ['', '', safe(name), safe(email)]
row_num  = len(all_values) + 1   # 1-indexed row where this will land

try:
    ws.append_rows(
        [new_row],
        value_input_option='USER_ENTERED',
        insert_data_option='OVERWRITE',
        table_range='A1',
    )
except Exception as e:
    print(json.dumps({"error": f"Could not write row: {str(e)}"}))
    sys.exit(1)

# Right-align the tester name in column C
try:
    ws.format(f'C{row_num}', {'horizontalAlignment': 'RIGHT'})
except Exception:
    pass  # non-fatal — alignment is cosmetic

print(json.dumps({
    "ok": True,
    "row_num": row_num,
    "row": {"Date": "", "Event": "", "Observation": name, "Solution": email},
}))
