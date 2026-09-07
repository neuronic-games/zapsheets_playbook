# gupdatecontract.py — update a contract row in the contracts sheet by ID
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: contract_id, game, client, target_start_date, target_end_date,
#            start_date, end_date, quote, payment, notes

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

contract_id       = str(data.get('contract_id',       '')).strip()
game              = data.get('game',              '').strip()
client            = data.get('client',            '').strip()
target_start_date = data.get('target_start_date', '').strip()
target_end_date   = data.get('target_end_date',   '').strip()
start_date        = data.get('start_date',        '').strip()
end_date          = data.get('end_date',          '').strip()
quote             = data.get('quote',             '').strip()
payment           = data.get('payment',           '').strip()
notes             = data.get('notes',             '').strip()

if not contract_id:
    print(json.dumps({"error": "contract_id is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find contracts worksheet (case-insensitive)
all_ws = wb.worksheets()
ws = next((w for w in all_ws if w.title.lower() == 'contracts'), None)
if not ws:
    print(json.dumps({"error": "contracts sheet not found"}))
    sys.exit(1)

# Read all values to find the row
records = ws.get_all_values()
if not records:
    print(json.dumps({"error": "contracts sheet is empty"}))
    sys.exit(1)

headers = [h.strip() for h in records[0]]

# Map header names to column indices
def col(name):
    try:
        return headers.index(name)
    except ValueError:
        return -1

id_col = col('ID')
if id_col < 0:
    print(json.dumps({"error": "ID column not found in contracts sheet"}))
    sys.exit(1)

# Find the row matching contract_id
row_idx = None
for i, row in enumerate(records[1:], start=2):  # 1-based, row 1 is header
    if str(row[id_col]).strip() == contract_id:
        row_idx = i
        break

if row_idx is None:
    print(json.dumps({"error": f"Contract ID {contract_id} not found"}))
    sys.exit(1)

# Build update map: column letter → value
FIELD_MAP = {
    'Game':              game,
    'Client':            client,
    'Target Start Date': target_start_date,
    'Target End Date':   target_end_date,
    'Start Date':        start_date,
    'End Date':          end_date,
    'Quote':             quote,
    'Payment':           payment,
    'Notes':             notes,
}

try:
    updates = []
    for field, value in FIELD_MAP.items():
        c_idx = col(field)
        if c_idx >= 0:
            col_letter = chr(ord('A') + c_idx)
            cell = f'{col_letter}{row_idx}'
            updates.append({'range': cell, 'values': [[value]]})
    if updates:
        ws.batch_update(updates, value_input_option='USER_ENTERED')
    print(json.dumps({"ok": True, "row": row_idx}))
except Exception as e:
    print(json.dumps({"error": f"Update failed: {str(e)}"}))
    sys.exit(1)
