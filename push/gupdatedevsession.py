# gupdatedevsession.py — replace all rows of a session in a [GameName] dev tab.
#
# Finds the session header row by matching orig_date + orig_event, deletes all
# rows of that session, then inserts the replacement rows at the same position.
#
# Arg: {sheet_id}|{tab_name}|{base64_json}
# JSON: {
#   "orig_date":  "2026-08-31",   ← used to locate the existing session
#   "orig_event": "Playtest 6",
#   "rows": [                     ← replacement rows (header + testers + obs)
#     ["2026-08-31", "Playtest 6", "Local", ""],
#     ["", "", "Alice",  "alice@example.com"],
#     ["", "", "Cool",   "Yes"]
#   ]
# }

import gspread
import sys, os, json, base64, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets formula interpretation."""
    s = (str(v) if v is not None else '').strip()
    return ("'" + s) if s else ''

def clean(v):
    """Strip the safe_str apostrophe prefix if present (returned by get_all_values)."""
    return v.strip().lstrip("'").strip() if v else ''

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

raw = sys.argv[1] if len(sys.argv) > 1 else ''
parts = raw.split('|', 2)
if len(parts) < 3:
    print(json.dumps({"error": "Expected argument: sheet_id|tab_name|base64_json"}))
    sys.exit(1)

sheet_id = parts[0].strip()
tab_name = parts[1].strip()
try:
    data = json.loads(base64.b64decode(parts[2]).decode('utf-8'))
except Exception as e:
    print(json.dumps({"error": f"Could not decode payload: {str(e)}"}))
    sys.exit(1)

orig_date  = data.get('orig_date',  '').strip()
orig_event = data.get('orig_event', '').strip()
new_rows   = data.get('rows', [])   # list of [date, event, observation, solution]

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

existing_tabs = {w.title: w for w in wb.worksheets()}
if tab_name not in existing_tabs:
    print(json.dumps({"error": f"Tab '{tab_name}' not found"}))
    sys.exit(1)

ws = existing_tabs[tab_name]

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if len(all_values) < 2:
    print(json.dumps({"error": "Sheet has no data rows"}))
    sys.exit(1)

# Find the session header row.
# all_values[0] = sheet row 1 (header). Data rows start at all_values[1] = sheet row 2.
# enumerate(..., start=2) maps list index i → sheet row i (1-indexed).
header_sheet_row = None
for i, row in enumerate(all_values[1:], start=2):
    row_date  = clean(row[0] if len(row) > 0 else '')
    row_event = clean(row[1] if len(row) > 1 else '')
    if row_date == orig_date and row_event == orig_event:
        header_sheet_row = i
        break

if header_sheet_row is None:
    print(json.dumps({"error": f"Session not found: date={orig_date!r} event={orig_event!r}"}))
    sys.exit(1)

# Find the end of the session: first subsequent row with non-empty date or event.
# all_values[header_sheet_row] = sheet row (header_sheet_row + 1), i.e. the first row after.
end_sheet_row = len(all_values) + 1   # exclusive; default = one past last row
for i, row in enumerate(all_values[header_sheet_row:], start=header_sheet_row + 1):
    row_date  = clean(row[0] if len(row) > 0 else '')
    row_event = clean(row[1] if len(row) > 1 else '')
    if row_date or row_event:
        end_sheet_row = i
        break

session_row_count = end_sheet_row - header_sheet_row

# Delete old session rows (gspread delete_rows: 1-indexed, inclusive on both ends).
try:
    ws.delete_rows(header_sheet_row, end_sheet_row - 1)
except Exception as e:
    print(json.dumps({"error": f"Could not delete rows: {str(e)}"}))
    sys.exit(1)

# Insert replacement rows at the original header position.
if new_rows:
    values_to_insert = [[safe_str(cell) for cell in row] for row in new_rows]
    try:
        ws.insert_rows(
            values_to_insert,
            row=header_sheet_row,
            value_input_option='USER_ENTERED'
        )
    except Exception as e:
        print(json.dumps({"error": f"Could not insert rows: {str(e)}"}))
        sys.exit(1)

print(json.dumps({
    "ok": True,
    "header_row":     header_sheet_row,
    "deleted":        session_row_count,
    "rows_written":   len(new_rows),
}))
